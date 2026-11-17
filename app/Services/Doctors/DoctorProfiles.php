<?php

namespace App\Services\Doctors;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\MediaPurpose;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Models\DoctorDocument;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The doctor's own record (app spec §4): read and edited by the doctor, documents uploaded, and
 * the whole submitted for the team's review once nothing is missing.
 */
class DoctorProfiles
{
    private const SCALARS = [
        'mdcn_number', 'specialty', 'sub_specialty', 'years_of_experience', 'current_institution', 'languages',
        'bio', 'fee_chat_kobo', 'fee_audio_kobo', 'fee_video_kobo', 'session_minutes', 'chat_window_hours',
        'follow_up_fee_kobo', 'bank_name', 'availability', 'is_available_now',
    ];

    public function __construct(
        private readonly MediaLibrary $media,
        private readonly DoctorVerification $verification,
    ) {}

    /**
     * The doctor's record, started blank on first read as the app's onboarding expects.
     */
    public function of(User $doctor): DoctorProfile
    {
        $profile = $doctor->doctorProfile()->first() ?? DB::transaction(function () use ($doctor) {
            $doctor->newQuery()->lockForUpdate()->find($doctor->id);

            return $doctor->doctorProfile()->first()
                ?? $doctor->doctorProfile()->create();
        });

        // A read answers 200 even when it started the record.
        $profile->wasRecentlyCreated = false;

        return $profile->load(['user', 'photo', 'documents']);
    }

    /**
     * @param  array<string, mixed>  $data  `zUpdateDoctorProfileRequest`, validated: absent keys are left alone
     */
    public function update(User $doctor, array $data): DoctorProfile
    {
        $profile = $this->of($doctor);

        DB::transaction(function () use ($doctor, $profile, $data) {
            $this->guardMdcnNumber($profile, $data);

            $profile->fill(Arr::only($data, self::SCALARS));

            if (array_key_exists('availability', $data)) {
                $profile->availability = array_map(fn(array $window) => Arr::only($window, ['day', 'start', 'end']), $data['availability']);
            }

            if (array_key_exists('bank_account_number', $data)) {
                $profile->bank_account_number = $data['bank_account_number'];
                $profile->bank_account_last4 = substr($data['bank_account_number'], -4);
            }

            if (array_key_exists('photo_url', $data)) {
                $this->setPhoto($doctor, $profile, $data['photo_url']);
            }

            if ($profile->isDirty(['bank_name', 'bank_account_last4']) && $profile->bank_name !== null && $profile->bank_account_last4 !== null) {
                $this->markUploaded($profile, DocumentKind::BankDetails, null);
            }

            $profile->save();
        });

        return $profile->load(['photo', 'documents']);
    }

    public function uploadDocument(User $doctor, DocumentKind $kind, string $url): DoctorProfile
    {
        $profile = $this->of($doctor);

        DB::transaction(function () use ($doctor, $profile, $kind, $url) {
            $media = $this->media->claim($doctor, $url, MediaPurpose::DoctorDocument, 'file.uri');

            $this->markUploaded($profile, $kind, $media->id);
        });

        return $profile->load('documents');
    }

    /**
     * Into the review queue (spec §4.3). Refused while anything is missing, naming each thing, since
     * a rejected submission costs the doctor 48 hours. A pending submission stays where it queued.
     */
    public function submit(User $doctor): DoctorProfile
    {
        $profile = $this->of($doctor);

        DB::transaction(function () use ($profile) {
            $profile->newQuery()->lockForUpdate()->find($profile->id);

            $this->verification->submit($profile, $this->missing($profile));
        });

        return $profile;
    }

    /**
     * What a submission still lacks, by the names the app shows (its mock's list). A rejected
     * document counts as missing until it is uploaded again.
     *
     * @return list<string>
     */
    private function missing(DoctorProfile $profile): array
    {
        $fields = [
            'mdcn_number' => $profile->mdcn_number === null,
            'specialty' => $profile->specialty === null,
            'years_of_experience' => $profile->years_of_experience === null,
            'languages' => $profile->languages === [],
            'bio' => blank($profile->bio),
            'fees' => $profile->fee_chat_kobo === null || $profile->fee_audio_kobo === null || $profile->fee_video_kobo === null,
            'bank_details' => $profile->bank_name === null || $profile->bank_account_last4 === null,
        ];

        $missing = array_keys(array_filter($fields));

        foreach (DocumentKind::required($profile->sub_specialty !== null) as $kind) {
            $status = $profile->document($kind)?->status;

            if ($kind !== DocumentKind::BankDetails && ($status === null || $status === DocumentStatus::Rejected)) {
                $missing[] = $kind->value;
            }
        }

        return $missing;
    }

    /**
     * The number the review checks against the MDCN register is fixed once submitted.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardMdcnNumber(DoctorProfile $profile, array $data): void
    {
        $changing = array_key_exists('mdcn_number', $data) && $data['mdcn_number'] !== $profile->mdcn_number;
        $underReview = $profile->submitted_at !== null && $profile->verification_status !== VerificationStatus::Rejected;

        if ($changing && $underReview) {
            throw ApiException::unprocessable('MDCN_NUMBER_LOCKED', 'mdcn_number');
        }
    }

    /**
     * A new upload (or new bank details) replaces the last and waits for review again.
     */
    private function markUploaded(DoctorProfile $profile, DocumentKind $kind, ?int $mediaId): void
    {
        $document = $profile->document($kind) ?? new DoctorDocument([
            'doctor_profile_id' => $profile->id,
            'kind' => $kind,
        ]);

        $previous = $document->media;

        $document->fill([
            'status' => DocumentStatus::Uploaded,
            'media_id' => $mediaId,
            'uploaded_at' => now(),
            'rejection_reason' => null,
            'reviewed_at' => null,
        ]);
        $document->save();

        if ($previous !== null && $previous->id !== $mediaId) {
            $this->media->discard($previous);
        }

        $profile->unsetRelation('documents');
        $profile->load('documents');
    }

    private function setPhoto(User $doctor, DoctorProfile $profile, ?string $url): void
    {
        $previous = $profile->photo;
        $media = $url === null
            ? null
            : $this->media->claim($doctor, $url, MediaPurpose::DoctorPhoto, 'photo_url');

        $profile->photo_media_id = $media?->id;
        $profile->setRelation('photo', $media);

        if ($previous !== null && ! $previous->is($media)) {
            $this->media->discard($previous);
        }
    }
}

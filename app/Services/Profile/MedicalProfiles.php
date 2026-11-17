<?php

namespace App\Services\Profile;

use App\Enums\MediaPurpose;
use App\Exceptions\ApiException;
use App\Models\MedicalProfile;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Support\NigerianGeography;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The medical profile (app spec §3): the required core at onboarding, the rest progressively.
 * Only its owner reads or writes it; anyone else is told it does not exist.
 */
class MedicalProfiles
{
    private const SCALARS = [
        'date_of_birth', 'gender', 'blood_type', 'genotype', 'state_of_residence', 'lga', 'drug_allergies',
        'height_cm', 'weight_kg', 'existing_conditions', 'past_surgeries', 'food_allergies', 'allergy_notes',
    ];

    private const HMO = [
        'provider' => 'hmo_provider',
        'member_id' => 'hmo_member_id',
        'plan_type' => 'hmo_plan_type',
        'expiry_date' => 'hmo_expiry_date',
        'nhia_number' => 'nhia_number',
    ];

    public function __construct(
        private readonly MediaLibrary $media,
    ) {}

    public function forOwner(User $caller, string $userId): MedicalProfile
    {
        if ($caller->uuid !== $userId) {
            throw ApiException::notFound();
        }

        return $caller->medicalProfile()->first()
            ?? throw ApiException::notFound(MedicalProfile::NOT_FOUND);
    }

    /**
     * The state the patient lives in, for ranking what is near them; null without a profile.
     */
    public function stateOf(User $user): ?string
    {
        return $user
            ->medicalProfile()
            ->value('state_of_residence');
    }

    /**
     * @param  array<string, mixed>  $data  `zCreateProfileRequest`, validated
     */
    public function create(User $user, array $data): MedicalProfile
    {
        return DB::transaction(function () use ($user, $data) {
            $user->newQuery()->lockForUpdate()->find($user->id);

            if ($user->medicalProfile()->exists()) {
                throw ApiException::conflict('PROFILE_EXISTS');
            }

            $profile = new MedicalProfile(['user_id' => $user->id]);

            $this->apply($profile, $data);
            $profile->save();

            return $profile;
        });
    }

    /**
     * @param  array<string, mixed>  $data  `zUpdateProfileRequest`, validated: absent keys are left alone
     */
    public function update(User $caller, string $userId, array $data): MedicalProfile
    {
        $profile = $this->forOwner($caller, $userId);

        return DB::transaction(function () use ($caller, $profile, $data) {
            $this->apply($profile, $data);

            if (array_key_exists('profile_photo_url', $data)) {
                $this->setPhoto($caller, $profile, $data['profile_photo_url']);
            }

            $profile->save();

            return $profile;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function apply(MedicalProfile $profile, array $data): void
    {
        $profile->fill(Arr::only($data, self::SCALARS));

        if (isset($data['drug_allergies'])) {
            $profile->drug_allergies = array_map(fn(array $allergy) => [
                'drug_name' => $allergy['drug_name'],
                'reaction_type' => $allergy['reaction_type'] ?? null,
                'severity' => $allergy['severity'],
            ], $data['drug_allergies']);
        }

        if (isset($data['past_surgeries'])) {
            $profile->past_surgeries = array_map(fn(array $surgery) => [
                'surgery_name' => $surgery['surgery_name'],
                'date' => $surgery['date'] ?? null,
                'hospital' => $surgery['hospital'] ?? null,
            ], $data['past_surgeries']);
        }

        if (isset($data['emergency_contact'])) {
            $this->setContact($profile, $data['emergency_contact']);
        }

        foreach (self::HMO as $key => $column) {
            if (isset($data['hmo']) && array_key_exists($key, $data['hmo'])) {
                $profile->{$column} = $data['hmo'][$key];
            }
        }

        $this->settleLga($profile, array_key_exists('lga', $data));
    }

    /**
     * @param  array{name: string, phone: string, relationship: string, address?: string|null}  $contact
     */
    private function setContact(MedicalProfile $profile, array $contact): void
    {
        $phone = PhoneNumber::normalise($contact['phone']);

        // A new number has not been confirmed (spec §3.2.5).
        if ($profile->contact_phone !== $phone) {
            $profile->contact_verified_at = null;
        }

        $profile->fill([
            'contact_name' => $contact['name'],
            'contact_phone' => $phone,
            'contact_relationship' => $contact['relationship'],
            'contact_address' => $contact['address'] ?? null,
        ]);
    }

    /**
     * The area must belong to the state (app handover §5.5). A move to another state drops an area
     * the request did not restate, rather than keep one that is now wrong.
     */
    private function settleLga(MedicalProfile $profile, bool $sent): void
    {
        if ($profile->lga === null || NigerianGeography::isLgaOf($profile->state_of_residence, $profile->lga)) {
            return;
        }

        if ($sent) {
            throw ApiException::badRequest('VALIDATION_FAILED', 'lga', __('profile.lga_not_in_state'));
        }

        $profile->lga = null;
    }

    private function setPhoto(User $owner, MedicalProfile $profile, ?string $url): void
    {
        $previous = $profile->photo;
        $media = $url === null
            ? null
            : $this->media->claim($owner, $url, MediaPurpose::ProfilePhoto, 'profile_photo_url');

        $profile->photo_media_id = $media?->id;
        $profile->setRelation('photo', $media);

        if ($previous !== null && ! $previous->is($media)) {
            $this->media->discard($previous);
        }
    }
}

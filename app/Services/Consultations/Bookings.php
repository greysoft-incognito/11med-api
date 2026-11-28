<?php

namespace App\Services\Consultations;

use App\Enums\AccountKind;
use App\Enums\ConsentContext;
use App\Enums\ConsultationStatus;
use App\Enums\PaymentChannel;
use App\Enums\SessionType;
use App\Exceptions\ApiException;
use App\Models\ConsentRecord;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Services\Doctors\DoctorDirectory;
use App\Services\Doctors\DoctorSlots;
use App\Services\Identity\PolicyConsent;
use App\Services\Payments\Payments;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Booking a consultation (app spec §7.2, handover §13 and §16): now, with a doctor available this
 * minute, or a slot the doctor's hours offer; a follow-up at the follow-up fee; paid at booking.
 */
class Bookings
{
    public function __construct(
        private readonly PolicyConsent $policy,
        private readonly DoctorDirectory $directory,
        private readonly DoctorSlots $slots,
        private readonly ConsultationClock $clock,
        private readonly ConsultationLifecycle $lifecycle,
        private readonly Payments $payments,
        private readonly PaidConsultations $paid,
    ) {}

    /**
     * @param  array{doctor_id: string, session_type: string, complaint: string, scheduled_for: ?string, profile_shared: bool, follow_up_of?: ?string, channel?: ?string}  $data
     */
    public function book(User $patient, array $data): Consultation
    {
        $doctor = $this->directory->bookable($data['doctor_id']);
        $type = SessionType::from($data['session_type']);
        $now = CarbonImmutable::now();

        $this->refuseIfEngaged($patient, $doctor);

        $previous = $this->followUpOf($patient, $doctor, $data['follow_up_of'] ?? null, $now);
        $slot = $this->slot($doctor, $data['scheduled_for'] ?? null, $now);
        $fee = $this->fee($doctor, $type, $previous);

        $consultation = $this->hold($doctor, $slot, fn() => $this->open($patient, $doctor, $type, $data, $slot, $fee, $previous, $now));

        if ($fee === 0) {
            $this->paid->settle($consultation);
        } else {
            $channel = PaymentChannel::tryFrom((string) ($data['channel'] ?? ''));

            $this->payments->initialise([$consultation], $patient, $channel);
        }

        return $consultation->refresh();
    }

    private function refuseIfEngaged(User $patient, DoctorProfile $doctor): void
    {
        $engaged = Consultation::query()
            ->where('patient_id', $patient->id)
            ->where('doctor_id', $doctor->user_id)
            ->whereIn('status', ConsultationStatus::engaged())
            ->exists();

        if ($engaged) {
            throw ApiException::unprocessable('DOCTOR_UNAVAILABLE');
        }
    }

    private function followUpOf(User $patient, DoctorProfile $doctor, ?string $id, CarbonImmutable $now): ?Consultation
    {
        if ($id === null) {
            return null;
        }

        $previous = Consultation::query()
            ->where('patient_id', $patient->id)
            ->where('uuid', $id)
            ->first();

        if ($previous === null) {
            throw ApiException::notFound(Consultation::NOT_FOUND);
        }

        if ($previous->doctor_id !== $doctor->user_id) {
            throw ApiException::unprocessable('FOLLOW_UP_OTHER_DOCTOR', 'follow_up_of');
        }

        if ($previous->status !== ConsultationStatus::Completed || $previous->ended_at === null) {
            throw ApiException::unprocessable('FOLLOW_UP_NOT_FINISHED', 'follow_up_of');
        }

        if ($previous->ended_at->lessThan($now->subDays(config('elevenmed.consultations.follow_up_days')))) {
            throw ApiException::unprocessable('FOLLOW_UP_EXPIRED', 'follow_up_of');
        }

        return $previous;
    }

    /**
     * A time more than a minute ahead is a slot the hours must offer; anything else is now, which
     * needs the doctor available this minute.
     */
    private function slot(DoctorProfile $doctor, ?string $requested, CarbonImmutable $now): ?CarbonImmutable
    {
        $at = $requested === null ? null : CarbonImmutable::parse($requested)->utc();

        if ($at !== null && $at->greaterThan($now->addMinute())) {
            if (! $this->slots->offers($doctor, $at, $now)) {
                throw ApiException::unprocessable('SLOT_UNAVAILABLE', 'scheduled_for');
            }

            return $at;
        }

        if (! $doctor->is_available_now) {
            throw ApiException::unprocessable('DOCTOR_UNAVAILABLE');
        }

        return null;
    }

    private function fee(DoctorProfile $doctor, SessionType $type, ?Consultation $previous): int
    {
        if ($previous !== null && $doctor->follow_up_fee_kobo !== null) {
            return $doctor->follow_up_fee_kobo;
        }

        $fee = match ($type) {
            SessionType::Chat => $doctor->fee_chat_kobo,
            SessionType::Audio => $doctor->fee_audio_kobo,
            SessionType::Video => $doctor->fee_video_kobo,
        };

        if ($fee === null) {
            throw ApiException::unprocessable('SESSION_TYPE_UNAVAILABLE', 'session_type');
        }

        return $fee;
    }

    /**
     * Bookings for one doctor queue on the profile row, so a slot is checked and held atomically; the
     * partial unique index is the last word.
     */
    private function hold(DoctorProfile $doctor, ?CarbonImmutable $slot, callable $open): Consultation
    {
        try {
            return DB::transaction(function () use ($doctor, $slot, $open) {
                DoctorProfile::query()
                    ->whereKey($doctor->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($slot !== null && $this->isHeld($doctor, $slot)) {
                    throw ApiException::conflict('SLOT_TAKEN', 'scheduled_for');
                }

                return $open();
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('SLOT_TAKEN', 'scheduled_for');
        }
    }

    private function isHeld(DoctorProfile $doctor, CarbonImmutable $slot): bool
    {
        return Consultation::query()
            ->where('doctor_id', $doctor->user_id)
            ->where('scheduled', true)
            ->where('scheduled_for', $slot)
            ->whereIn('status', ConsultationStatus::live())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function open(User $patient, DoctorProfile $doctor, SessionType $type, array $data, ?CarbonImmutable $slot, int $fee, ?Consultation $previous, CarbonImmutable $now): Consultation
    {
        $length = $type === SessionType::Chat ? $doctor->chat_window_hours * 60 : $doctor->session_minutes;

        $consultation = $this->lifecycle->open([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->user_id,
            'doctor_name' => $doctor->user->full_name,
            'doctor_specialty' => $doctor->specialty,
            'session_type' => $type,
            'complaint' => trim($data['complaint']),
            'fee_kobo' => $fee,
            'scheduled_for' => $slot ?? $now,
            'scheduled' => $slot !== null,
            'length_minutes' => $length,
            'follow_up_of_id' => $previous?->id,
            'expires_at' => $this->clock->acceptBy($now, $slot),
            'profile_shared' => (bool) $data['profile_shared'],
        ]);

        if ($consultation->profile_shared) {
            $this->recordConsent($patient, $consultation, $now);
        }

        return $consultation;
    }

    /**
     * Sharing the profile with this doctor is a consent of its own (app spec §7.3, handover §3.2).
     */
    private function recordConsent(User $patient, Consultation $consultation, CarbonImmutable $now): void
    {
        $consent = new ConsentRecord([
            'user_id' => $patient->id,
            'context' => ConsentContext::ProfileSharing,
            'policy_version' => $this->policy->recordable(AccountKind::Patient),
            'accepted_at' => $now,
            'recorded_at' => $now,
        ]);

        $consent->subject()->associate($consultation);
        $consent->save();
    }
}

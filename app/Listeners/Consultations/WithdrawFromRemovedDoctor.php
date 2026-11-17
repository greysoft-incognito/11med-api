<?php

namespace App\Listeners\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\VerificationStatus;
use App\Events\Doctors\DoctorReviewed;
use App\Models\Consultation;
use App\Services\Consultations\ConsultationLifecycle;

/**
 * A doctor suspended, or rejected after approval, keeps no bookings that have not begun (portal
 * handover §6): each is cancelled, refunded and the patient told.
 */
class WithdrawFromRemovedDoctor
{
    public function __construct(
        private readonly ConsultationLifecycle $lifecycle,
    ) {}

    public function handle(DoctorReviewed $event): void
    {
        $status = $event->profile->verification_status;

        if ($event->previous !== VerificationStatus::Approved || $status === VerificationStatus::Approved) {
            return;
        }

        Consultation::query()
            ->where('doctor_id', $event->profile->user_id)
            ->whereIn('status', [ConsultationStatus::Pending, ConsultationStatus::Confirmed])
            ->select(['id'])
            ->chunkById(200, fn($chunk) => $chunk->each(fn(Consultation $consultation) => $this->lifecycle->withdraw($consultation)));
    }
}

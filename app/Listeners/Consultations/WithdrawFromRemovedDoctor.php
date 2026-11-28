<?php

namespace App\Listeners\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\VerificationStatus;
use App\Events\Accounts\AccountSuspended;
use App\Events\Doctors\DoctorReviewed;
use App\Models\Consultation;
use App\Services\Consultations\ConsultationLifecycle;

/**
 * A doctor suspended, rejected after approval, or whose account the team suspended, keeps no
 * bookings that have not begun (portal handover §6): each is cancelled, refunded and the patient told.
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

        $this->withdrawAll($event->profile->user_id);
    }

    public function handleSuspension(AccountSuspended $event): void
    {
        $this->withdrawAll($event->user->id);
    }

    private function withdrawAll(int $doctorId): void
    {
        Consultation::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [ConsultationStatus::Pending, ConsultationStatus::Confirmed])
            ->select(['id'])
            ->chunkById(200, fn($chunk) => $chunk->each(fn(Consultation $consultation) => $this->lifecycle->withdraw($consultation)));
    }
}

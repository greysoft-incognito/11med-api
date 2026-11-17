<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Services\Doctors\HeldSlots;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The slots a doctor's live scheduled bookings hold, for the directory's `/slots`.
 */
class ConsultationHeldSlots implements HeldSlots
{
    public function heldBetween(int $doctorUserId, CarbonInterface $from, CarbonInterface $to): array
    {
        return Consultation::query()
            ->where('doctor_id', $doctorUserId)
            ->where('scheduled', true)
            ->whereIn('status', ConsultationStatus::live())
            ->whereBetween('scheduled_for', [$from, $to])
            ->pluck('scheduled_for')
            ->map(fn(CarbonInterface $at) => CarbonImmutable::instance($at))
            ->values()
            ->all();
    }
}

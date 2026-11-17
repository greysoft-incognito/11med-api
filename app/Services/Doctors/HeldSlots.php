<?php

namespace App\Services\Doctors;

use App\Services\Consultations\ConsultationHeldSlots;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Bind;

/**
 * The slot instants a doctor already has a live booking for (pending, confirmed or in progress).
 */
#[Bind(ConsultationHeldSlots::class)]
interface HeldSlots
{
    /**
     * @return list<CarbonInterface>
     */
    public function heldBetween(int $doctorUserId, CarbonInterface $from, CarbonInterface $to): array;
}

<?php

namespace App\Services\Doctors;

use Carbon\CarbonInterface;

/**
 * No bookings exist until the Consultations module does.
 */
class NoHeldSlots implements HeldSlots
{
    public function heldBetween(int $doctorUserId, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }
}

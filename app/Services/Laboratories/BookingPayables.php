<?php

namespace App\Services\Laboratories;

use App\Exceptions\ApiException;
use App\Models\LabBooking;
use App\Models\User;
use App\Services\Payments\PayableTargets;

/**
 * The booking `POST payments` starts or restarts a payment for: the patient's own, alone.
 */
class BookingPayables implements PayableTargets
{
    public function resolve(string $targetId, User $payer): array
    {
        $booking = LabBooking::query()
            ->with(['patient', 'laboratory.organisation'])
            ->where('patient_id', $payer->id)
            ->where('uuid', $targetId)
            ->first();

        if ($booking === null) {
            throw ApiException::notFound(LabBooking::NOT_FOUND);
        }

        return [$booking];
    }
}

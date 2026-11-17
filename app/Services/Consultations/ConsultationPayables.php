<?php

namespace App\Services\Consultations;

use App\Exceptions\ApiException;
use App\Models\Consultation;
use App\Models\User;
use App\Services\Payments\PayableTargets;

/**
 * What `POST payments` with purpose `consultation` pays for: one of the payer's own bookings.
 */
class ConsultationPayables implements PayableTargets
{
    public function resolve(string $targetId, User $payer): array
    {
        $consultation = Consultation::query()
            ->where('patient_id', $payer->id)
            ->where('uuid', $targetId)
            ->first();

        if ($consultation === null) {
            throw ApiException::notFound(Consultation::NOT_FOUND);
        }

        return [$consultation];
    }
}

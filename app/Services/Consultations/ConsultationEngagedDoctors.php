<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use App\Services\Doctors\EngagedDoctors;

/**
 * The doctors a patient has a confirmed or open consultation with (app mock `engagedWith`).
 */
class ConsultationEngagedDoctors implements EngagedDoctors
{
    public function for(User $patient): array
    {
        return Consultation::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', ConsultationStatus::engaged())
            ->whereNotNull('doctor_id')
            ->distinct()
            ->pluck('doctor_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }
}

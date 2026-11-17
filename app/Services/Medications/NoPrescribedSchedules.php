<?php

namespace App\Services\Medications;

use App\Models\User;

/**
 * Adds nothing until the Medications module exists.
 */
class NoPrescribedSchedules implements PrescribedSchedules
{
    public function apply(User $patient, User $doctor, string $prescriptionId, array $drugs): array
    {
        return [];
    }
}

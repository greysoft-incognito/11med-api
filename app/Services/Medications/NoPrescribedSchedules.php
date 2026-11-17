<?php

namespace App\Services\Medications;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Adds nothing until the Medications module exists.
 */
class NoPrescribedSchedules implements PrescribedSchedules
{
    public function apply(User $patient, User $doctor, string $prescriptionId, string $diagnosis, array $drugs): Collection
    {
        return new Collection;
    }
}

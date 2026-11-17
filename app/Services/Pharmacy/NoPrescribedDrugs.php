<?php

namespace App\Services\Pharmacy;

use App\Models\User;

/**
 * No prescription exists until the Consultations module does.
 */
class NoPrescribedDrugs implements PrescribedDrugs
{
    public function names(User $patient): array
    {
        return [];
    }
}

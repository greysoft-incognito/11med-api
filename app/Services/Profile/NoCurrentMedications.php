<?php

namespace App\Services\Profile;

use App\Models\User;

/**
 * No schedules exist until the Medications module does.
 */
class NoCurrentMedications implements CurrentMedications
{
    public function forEmergency(User $patient): array
    {
        return [];
    }
}

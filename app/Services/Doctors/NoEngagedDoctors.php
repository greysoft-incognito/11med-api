<?php

namespace App\Services\Doctors;

use App\Models\User;

/**
 * No consultations exist until the Consultations module does.
 */
class NoEngagedDoctors implements EngagedDoctors
{
    public function for(User $patient): array
    {
        return [];
    }
}

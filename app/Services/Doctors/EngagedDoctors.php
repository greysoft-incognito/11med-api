<?php

namespace App\Services\Doctors;

use App\Models\User;
use App\Services\Consultations\ConsultationEngagedDoctors;
use Illuminate\Container\Attributes\Bind;

/**
 * The doctors a patient is already with (a confirmed or open consultation), who are not offered to
 * that patient as available now.
 */
#[Bind(ConsultationEngagedDoctors::class)]
interface EngagedDoctors
{
    /**
     * @return list<int> doctor user ids
     */
    public function for(User $patient): array;
}

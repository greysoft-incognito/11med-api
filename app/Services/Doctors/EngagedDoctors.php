<?php

namespace App\Services\Doctors;

use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * The doctors a patient is already with (a confirmed or open consultation), who are not offered to
 * that patient as available now. The Consultations module (M10) implements it.
 */
#[Bind(NoEngagedDoctors::class)]
interface EngagedDoctors
{
    /**
     * @return list<int> doctor user ids
     */
    public function for(User $patient): array;
}

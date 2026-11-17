<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Services\Medications\ScheduledMedications;
use Illuminate\Container\Attributes\Bind;

/**
 * What a patient takes now, for the emergency page.
 */
#[Bind(ScheduledMedications::class)]
interface CurrentMedications
{
    /**
     * @return list<array{name: string, strength: string, times: list<string>}>
     */
    public function forEmergency(User $patient): array;
}

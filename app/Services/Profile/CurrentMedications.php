<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * What a patient takes now, for the emergency page. The Medications module (M6) implements it.
 */
#[Bind(NoCurrentMedications::class)]
interface CurrentMedications
{
    /**
     * @return list<array{name: string, strength: string, times: list<string>}>
     */
    public function forEmergency(User $patient): array;
}

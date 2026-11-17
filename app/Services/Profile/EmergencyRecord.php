<?php

namespace App\Services\Profile;

use App\Models\MedicalProfile;
use App\Models\QrToken;
use App\Models\User;

/**
 * What one open of the emergency page shows (portal `zEmergencyRecord`): the fixed safe subset.
 */
final readonly class EmergencyRecord
{
    /**
     * @param  list<array{name: string, strength: string, times: list<string>}>  $medications
     */
    public function __construct(
        public User $patient,
        public ?MedicalProfile $profile,
        public array $medications,
        public QrToken $token,
    ) {}
}

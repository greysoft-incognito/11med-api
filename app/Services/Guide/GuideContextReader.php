<?php

namespace App\Services\Guide;

use App\Models\User;
use App\Services\Profile\CurrentMedications;

/**
 * Builds the guide's view of the record, in one place, and no more of it than `GuideContext` holds.
 */
class GuideContextReader
{
    public function __construct(
        private readonly CurrentMedications $medications,
    ) {}

    public function for(User $patient): GuideContext
    {
        $firstName = strtok(trim((string) $patient->full_name), ' ');
        $conditions = $patient->medicalProfile()->value('existing_conditions');

        return new GuideContext(
            $firstName === false ? null : $firstName,
            is_string($conditions) ? (json_decode($conditions, true) ?? []) : [],
            count($this->medications->forEmergency($patient)),
        );
    }
}

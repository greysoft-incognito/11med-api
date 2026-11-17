<?php

namespace App\Services\Medications;

use App\Models\DoseLog;
use App\Models\Medication;
use Carbon\CarbonImmutable;

/**
 * One dose (app `zDose`): a schedule and the instant it is due, with its answer when it has one.
 */
final readonly class Dose
{
    public function __construct(
        public Medication $medication,
        public CarbonImmutable $scheduledFor,
        public ?DoseLog $log = null,
    ) {}

    public function id(): string
    {
        return DoseId::compose($this->medication->uuid, $this->scheduledFor);
    }
}

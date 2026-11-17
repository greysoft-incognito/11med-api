<?php

namespace App\Events\Medications;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A dose passed its sixty-minute deadline unanswered and was recorded as missed, once per dose.
 * The Notifications module listens.
 */
class DoseMissed
{
    use Dispatchable;

    public function __construct(
        public readonly User $patient,
        public readonly string $medicationId,
        public readonly string $medicationName,
        public readonly string $strength,
        public readonly CarbonImmutable $scheduledFor,
    ) {}
}

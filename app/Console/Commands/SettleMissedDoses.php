<?php

namespace App\Console\Commands;

use App\Services\Medications\MissedDoses;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Marks doses missed sixty minutes past due (spec §6.3.1). Runs every minute.
 */
class SettleMissedDoses extends Command
{
    protected $signature = 'medications:settle-doses';

    protected $description = 'Mark unanswered and snoozed doses missed once their deadline has passed';

    public function handle(MissedDoses $missedDoses): int
    {
        $missed = $missedDoses->settle(CarbonImmutable::now());

        $this->info("{$missed} doses missed.");

        return self::SUCCESS;
    }
}

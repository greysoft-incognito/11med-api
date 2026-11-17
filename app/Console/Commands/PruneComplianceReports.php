<?php

namespace App\Console\Commands;

use App\Services\Medications\ComplianceReports;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Removes compliance report files whose links lapsed long ago. Runs hourly.
 */
class PruneComplianceReports extends Command
{
    protected $signature = 'medications:prune-reports';

    protected $description = 'Delete compliance report PDFs older than a day';

    public function handle(ComplianceReports $reports): int
    {
        $removed = $reports->prune(CarbonImmutable::now());

        $this->info("{$removed} reports removed.");

        return self::SUCCESS;
    }
}

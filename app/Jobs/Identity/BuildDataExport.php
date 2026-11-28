<?php

namespace App\Jobs\Identity;

use App\Enums\DataExportStatus;
use App\Models\DataExport;
use App\Services\Identity\Export\DataExports;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Builds one data export. Not retried: a failure marks it failed and the person may ask again.
 */
class BuildDataExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly int $exportId,
    ) {}

    public function handle(DataExports $exports): void
    {
        $export = DataExport::find($this->exportId);

        if ($export === null || $export->status !== DataExportStatus::Pending || $export->user === null) {
            return;
        }

        $exports->build($export);
    }
}

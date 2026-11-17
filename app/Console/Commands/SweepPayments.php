<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentSweep;
use Illuminate\Console\Command;

class SweepPayments extends Command
{
    protected $signature = 'payments:sweep';

    protected $description = 'Abandon payments pending past the timeout and retry refunds never sent to the gateway';

    public function handle(PaymentSweep $sweep): int
    {
        $counts = $sweep->run();

        $this->info("Abandoned {$counts['abandoned']}, settled {$counts['settled']}, refunds retried {$counts['refunds']}.");

        return self::SUCCESS;
    }
}

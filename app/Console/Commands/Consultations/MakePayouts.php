<?php

namespace App\Console\Commands\Consultations;

use App\Services\Consultations\Payouts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class MakePayouts extends Command
{
    protected $signature = 'consultations:payouts';

    protected $description = 'Make one payout per doctor from completed consultations not yet paid out';

    public function handle(Payouts $payouts): int
    {
        $made = $payouts->run(CarbonImmutable::now());

        $this->info("{$made} payouts made");

        return self::SUCCESS;
    }
}

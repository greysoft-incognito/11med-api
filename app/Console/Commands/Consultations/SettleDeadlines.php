<?php

namespace App\Console\Commands\Consultations;

use App\Services\Consultations\Deadlines;
use Illuminate\Console\Command;

class SettleDeadlines extends Command
{
    protected $signature = 'consultations:settle';

    protected $description = 'Expire and refund bookings past their accept or join deadline, and close chats past their window';

    public function handle(Deadlines $deadlines): int
    {
        $settled = $deadlines->settle();

        $this->info("{$settled['expired']} expired, {$settled['closed']} chats closed");

        return self::SUCCESS;
    }
}

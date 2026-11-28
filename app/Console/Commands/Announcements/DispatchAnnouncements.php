<?php

namespace App\Console\Commands\Announcements;

use App\Services\Announcements\Announcements;
use Illuminate\Console\Command;

class DispatchAnnouncements extends Command
{
    protected $signature = 'announcements:dispatch';

    protected $description = 'Start the scheduled announcements whose time has come';

    public function handle(Announcements $announcements): int
    {
        $count = $announcements->dispatchDue();

        $this->info("{$count} announcements started.");

        return self::SUCCESS;
    }
}

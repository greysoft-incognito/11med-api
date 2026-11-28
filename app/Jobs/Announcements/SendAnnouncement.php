<?php

namespace App\Jobs\Announcements;

use App\Models\Announcement;
use App\Services\Announcements\AnnouncementSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one announcement to its audience.
 */
class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public readonly int $announcementId,
    ) {}

    public function handle(AnnouncementSender $sender): void
    {
        $announcement = Announcement::find($this->announcementId);

        if ($announcement !== null) {
            $sender->deliver($announcement);
        }
    }
}

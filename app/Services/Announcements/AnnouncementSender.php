<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementStatus;
use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Database\Eloquent\Collection;

/**
 * Delivers an announcement as a notification row (and a push, when asked) to every account in its
 * audience, 200 at a time. Each copy carries a dedupe key, so a delivery that stopped part way
 * resumes without repeating anyone.
 */
class AnnouncementSender
{
    private const CHUNK = 200;

    public function __construct(
        private readonly AnnouncementAudiences $audiences,
        private readonly Notifier $notifier,
    ) {}

    public function deliver(Announcement $announcement): void
    {
        if ($announcement->status !== AnnouncementStatus::Sending) {
            return;
        }

        $data = [
            'target_id' => $announcement->uuid,
            'dedupe_key' => "announcement:{$announcement->uuid}",
            'push' => $announcement->push,
        ];

        $this->audiences
            ->query($announcement->audience)
            ->select(['users.id', 'users.uuid'])
            ->chunkById(self::CHUNK, fn(Collection $users) => $users->each(
                fn(User $user) => $this->notifier->send($user, NotificationCategory::Announcement, $announcement->title, $announcement->body, $data)
            ), 'users.id', 'id');

        $announcement->forceFill([
            'status' => AnnouncementStatus::Sent,
            'sent_at' => now(),
            'recipients' => $this->audiences->count($announcement->audience),
        ])->save();
    }
}

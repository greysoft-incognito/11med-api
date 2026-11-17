<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Jobs\Notifications\DeliverPush;
use App\Models\User;
use App\Services\Notifications\Push\PushMessage;
use Illuminate\Support\Str;

/**
 * Writes the history row when the user keeps in-app history for the category, then queues the
 * push when they allow it. A repeated dedupe key does neither again.
 */
class DeliveringNotifier implements Notifier
{
    public function __construct(
        private readonly NotificationPreferences $preferences,
        private readonly NotificationHistory $history,
    ) {}

    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        $preference = $this->preferences->for($to, $category);
        $targetId = $data['target_id'] ?? null;
        $notificationId = null;

        if ($preference->in_app) {
            [$notification, $fresh] = $this->history->record($to, $category, $title, $body, $targetId, $data['dedupe_key'] ?? null);

            if (! $fresh) {
                return $notification->uuid;
            }

            $notificationId = $notification->uuid;
        }

        if (($data['push'] ?? true) && $preference->push) {
            $message = new PushMessage($category, $notificationId ?? (string) Str::uuid7(), $targetId, $title, $body);

            DeliverPush::dispatch($to->id, $message)->afterCommit();
        }

        return $notificationId;
    }
}

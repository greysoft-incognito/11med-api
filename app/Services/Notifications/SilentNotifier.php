<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;

/**
 * Sends and records nothing, for a context that must not notify anyone.
 */
class SilentNotifier implements Notifier
{
    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        return null;
    }
}

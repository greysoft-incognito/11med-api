<?php

namespace App\Services\Notifications;

use App\Http\Paging\Page;

/**
 * One page of a user's notification history and how many of all their notifications are unread.
 */
final readonly class NotificationList
{
    public function __construct(
        public Page $page,
        public int $unreadCount,
    ) {}
}

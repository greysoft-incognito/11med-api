<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\ApiCollection;
use App\Services\Notifications\NotificationList;
use Illuminate\Http\Request;

/**
 * `zNotificationList`, paged (FE-003): `{ items, next_cursor, total }` plus the contract's
 * `unread_count` and `has_more`.
 */
class NotificationCollection extends ApiCollection
{
    public $collects = NotificationResource::class;

    private int $unreadCount;

    public function __construct(NotificationList $list)
    {
        $this->unreadCount = $list->unreadCount;

        parent::__construct($list->page);
    }

    public function toArray(Request $request): array
    {
        $page = parent::toArray($request);

        return [
            ...$page,
            'unread_count' => $this->unreadCount,
            'has_more' => $page['next_cursor'] !== null,
        ];
    }
}

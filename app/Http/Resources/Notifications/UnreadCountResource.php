<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * Portal `zUnreadCount`: what the bell shows, cheap enough to poll.
 *
 * @property int $resource
 */
class UnreadCountResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'unread_count' => $this->resource,
        ];
    }
}

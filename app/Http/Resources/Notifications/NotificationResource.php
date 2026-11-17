<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\ApiResource;
use App\Models\AppNotification;
use Illuminate\Http\Request;

/**
 * `zNotification`.
 *
 * @property AppNotification $resource
 */
class NotificationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->uuid,
            'category' => $this->resource->category->value,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'target_id' => $this->resource->target_id,
            'read_at' => $this->iso($this->resource->read_at),
            'created_at' => $this->iso($this->resource->created_at),
        ];
    }
}

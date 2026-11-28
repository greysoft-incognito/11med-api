<?php

namespace App\Http\Resources\Announcements;

use App\Http\Resources\ApiResource;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * Portal `zAnnouncement`.
 *
 * @property Announcement $resource
 */
class AnnouncementResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $announcement = $this->resource;

        return [
            'id' => $announcement->uuid,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'audience' => $announcement->audience->value,
            'push' => $announcement->push,
            'status' => $announcement->status->value,
            'send_at' => $this->iso($announcement->send_at),
            'sent_at' => $this->iso($announcement->sent_at),
            'cancelled_at' => $this->iso($announcement->cancelled_at),
            'recipients' => $announcement->recipients,
            'created_by_name' => $announcement->author?->full_name,
            'created_at' => $this->iso($announcement->created_at),
        ];
    }
}

<?php

namespace App\Http\Resources\Support;

use App\Enums\MediaPurpose;
use App\Http\Resources\ApiResource;
use App\Models\SupportMessage;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zSupportMessage`. An attachment's URL is signed afresh on every read; a picture carries no name.
 *
 * @property SupportMessage $resource
 */
class SupportMessageResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $attachment = $message->attachment;

        return [
            'id' => $message->uuid,
            'from' => $message->from_team ? 'team' : 'requester',
            'author_name' => $message->author?->full_name,
            'body' => $message->body,
            'attachment_url' => $attachment === null ? null : app(MediaLibrary::class)->url($attachment),
            'attachment_name' => $attachment === null || MediaPurpose::isImage($attachment->content_type) ? null : $attachment->name,
            'attachment_content_type' => $attachment?->content_type,
            'sent_at' => $this->iso($message->sent_at),
        ];
    }
}

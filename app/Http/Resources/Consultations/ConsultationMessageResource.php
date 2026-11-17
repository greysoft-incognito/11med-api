<?php

namespace App\Http\Resources\Consultations;

use App\Enums\MediaPurpose;
use App\Http\Resources\ApiResource;
use App\Models\ConsultationMessage;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zConsultationMessage`. An attachment's URL is signed afresh on every read (FE-010); a picture
 * carries no name.
 *
 * @property ConsultationMessage $resource
 */
class ConsultationMessageResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $attachment = $message->attachment;

        return [
            'id' => $message->uuid,
            'consultation_id' => $message->consultation->uuid,
            'sender' => $message->sender->value,
            'body' => $message->body,
            'attachment_url' => $attachment === null ? null : app(MediaLibrary::class)->url($attachment),
            'attachment_name' => $attachment === null || MediaPurpose::isImage($attachment->content_type) ? null : $attachment->name,
            'attachment_content_type' => $attachment?->content_type,
            'sent_at' => $this->iso($message->sent_at),
        ];
    }
}

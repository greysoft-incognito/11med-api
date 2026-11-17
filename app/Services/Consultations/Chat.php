<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\MediaPurpose;
use App\Enums\MessageSender;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\User;
use App\Services\Media\MediaLibrary;

/**
 * A consultation's chat (app spec §7.4): read by either side at any time, written only while the
 * session is open. An attachment is one of the sender's own uploads for `message_attachment`.
 */
class Chat
{
    public function __construct(
        private readonly MediaLibrary $media,
    ) {}

    /**
     * Oldest first, as a conversation reads.
     */
    public function messages(Consultation $consultation, ListQuery $list): Page
    {
        $query = ConsultationMessage::query()
            ->with('attachment')
            ->where('consultation_id', $consultation->id)
            ->orderBy('sent_at')
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * @param  array{body?: ?string, attachment_url?: ?string}  $data
     */
    public function send(Consultation $consultation, User $author, MessageSender $sender, array $data, string $closedCode): ConsultationMessage
    {
        if ($consultation->status !== ConsultationStatus::InProgress) {
            throw ApiException::unprocessable($closedCode);
        }

        $url = $data['attachment_url'] ?? null;
        $attachment = $url === null ? null : $this->media->claim($author, $url, MediaPurpose::MessageAttachment, 'attachment_url');

        $message = ConsultationMessage::create([
            'consultation_id' => $consultation->id,
            'sender' => $sender,
            'body' => trim((string) ($data['body'] ?? '')),
            'attachment_media_id' => $attachment?->id,
            'sent_at' => now(),
        ]);

        return $message->setRelation('attachment', $attachment);
    }
}

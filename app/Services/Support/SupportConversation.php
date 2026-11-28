<?php

namespace App\Services\Support;

use App\Enums\MediaPurpose;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Media\MediaLibrary;

/**
 * A ticket's messages, read oldest first by either side, and the one writer of them. An
 * attachment is the requester's own upload for `support_attachment`.
 */
class SupportConversation
{
    public function __construct(
        private readonly MediaLibrary $media,
    ) {}

    public function messages(SupportTicket $ticket, ListQuery $list): Page
    {
        $query = SupportMessage::query()
            ->with(['attachment', 'author'])
            ->where('ticket_id', $ticket->id)
            ->orderBy('sent_at')
            ->orderBy('id');

        return $list->paginate($query);
    }

    public function write(SupportTicket $ticket, User $author, bool $fromTeam, string $body, ?string $attachmentUrl): SupportMessage
    {
        $attachment = $attachmentUrl === null
            ? null
            : $this->media->claim($author, $attachmentUrl, MediaPurpose::SupportAttachment, 'attachment_url');

        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $author->id,
            'from_team' => $fromTeam,
            'body' => trim($body),
            'attachment_media_id' => $attachment?->id,
            'sent_at' => now(),
        ]);

        $ticket->forceFill(['last_activity_at' => $message->sent_at])->save();

        return $message
            ->setRelation('attachment', $attachment)
            ->setRelation('author', $author)
            ->setRelation('ticket', $ticket);
    }
}

<?php

namespace App\Services\Support;

use App\Enums\SupportCategory;
use App\Enums\SupportLinkKind;
use App\Enums\SupportStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Contact support, as the requester sees it: open a ticket, list their own, read and add to the
 * conversation until the team closes it. Another account's ticket is 404.
 */
class SupportTickets
{
    public function __construct(
        private readonly SupportConversation $conversation,
        private readonly SupportLinks $links,
    ) {}

    /**
     * @param  array{category: string, subject: string, message: string, attachment_url?: ?string, link?: ?array{kind: string, id: string}}  $input
     */
    public function open(User $requester, array $input): SupportTicket
    {
        $link = $input['link'] ?? null;
        $kind = $link === null ? null : SupportLinkKind::from($link['kind']);

        if ($kind !== null) {
            $this->links->ensureVisible($requester, $kind, $link['id']);
        }

        return DB::transaction(function () use ($requester, $input, $kind, $link) {
            $ticket = SupportTicket::create([
                'requester_id' => $requester->id,
                'category' => SupportCategory::from($input['category']),
                'subject' => trim($input['subject']),
                'status' => SupportStatus::Open,
                'link_kind' => $kind,
                'link_id' => $link === null ? null : strtolower($link['id']),
                'last_activity_at' => now(),
            ]);

            $this->conversation->write($ticket, $requester, false, $input['message'], $input['attachment_url'] ?? null);

            return $ticket;
        });
    }

    /**
     * The requester's tickets, the most recently active first.
     */
    public function list(User $requester, ListQuery $list): Page
    {
        $query = SupportTicket::query()
            ->where('requester_id', $requester->id)
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    public function find(User $requester, string $id): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->where('requester_id', $requester->id)
            ->where('uuid', $id)
            ->first();

        if ($ticket === null) {
            throw ApiException::notFound(SupportTicket::NOT_FOUND);
        }

        return $ticket;
    }

    public function messages(User $requester, string $id, ListQuery $list): Page
    {
        return $this->conversation->messages($this->find($requester, $id), $list);
    }

    /**
     * A follow-up from the requester puts the ticket back in the team's queue.
     */
    public function reply(User $requester, string $id, string $body, ?string $attachmentUrl): SupportMessage
    {
        return DB::transaction(function () use ($requester, $id, $body, $attachmentUrl) {
            $ticket = SupportTicket::query()
                ->whereKey($this->find($requester, $id)->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ticket->status === SupportStatus::Closed) {
                throw ApiException::unprocessable('TICKET_CLOSED');
            }

            $ticket->status = SupportStatus::Open;

            return $this->conversation->write($ticket, $requester, false, $body, $attachmentUrl);
        });
    }
}

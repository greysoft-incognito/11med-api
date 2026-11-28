<?php

namespace App\Services\Support;

use App\Enums\AdminPermission;
use App\Enums\NotificationCategory;
use App\Enums\SupportStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Services\Notifications\Notifier;
use App\Services\Staff\PermissionHolders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The team's support queue: every ticket, paged and filtered; assigned to a team member who may
 * reply; answered, which tells the requester through the `Notifier`; and closed. Each write is audited.
 */
class SupportQueue
{
    public function __construct(
        private readonly SupportConversation $conversation,
        private readonly PermissionHolders $holders,
        private readonly Notifier $notifier,
        private readonly AuditLog $audit,
    ) {}

    /**
     * Most recently active first. `assigned` is `me`, `unassigned` or absent.
     *
     * @param  array{status?: ?string, category?: ?string, assigned?: ?string, q?: ?string}  $filters
     */
    public function list(User $admin, array $filters, ListQuery $list): Page
    {
        $q = trim((string) ($filters['q'] ?? ''));

        $query = SupportTicket::query()
            ->with(['requester.organisation', 'requester.roles', 'assignee'])
            ->when($filters['status'] ?? null, fn(Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn(Builder $query, string $category) => $query->where('category', $category))
            ->when(($filters['assigned'] ?? null) === 'me', fn(Builder $query) => $query->where('assignee_id', $admin->id))
            ->when(($filters['assigned'] ?? null) === 'unassigned', fn(Builder $query) => $query->whereNull('assignee_id'))
            ->when($q !== '', fn(Builder $query) => $query->where(fn(Builder $query) => $query
                ->whereLike('subject', "%{$q}%")
                ->orWhereHas('requester', fn(Builder $query) => $query
                    ->whereLike('full_name', "%{$q}%")
                    ->orWhereLike('email', "%{$q}%")
                    ->orWhereLike('phone', "%{$q}%"))))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    public function find(string $id): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->with(['requester.organisation', 'requester.roles', 'assignee'])
            ->where('uuid', $id)
            ->first();

        if ($ticket === null) {
            throw ApiException::notFound(SupportTicket::NOT_FOUND);
        }

        return $ticket;
    }

    public function messages(string $id, ListQuery $list): Page
    {
        return $this->conversation->messages($this->find($id), $list);
    }

    /**
     * Give the ticket to a team member who can reply, or to nobody.
     */
    public function assign(User $admin, string $id, ?string $assigneeId): SupportTicket
    {
        return DB::transaction(function () use ($admin, $id, $assigneeId) {
            $ticket = $this->locked($id);
            $assignee = null;

            if ($assigneeId !== null) {
                $assignee = $this->holders
                    ->holders(AdminPermission::SupportReply)
                    ->where('users.uuid', $assigneeId)
                    ->first();

                if ($assignee === null) {
                    throw ApiException::unprocessable('ASSIGNEE_NOT_SUPPORT', 'assignee_id');
                }
            }

            $ticket->assignee_id = $assignee?->id;
            $ticket->save();

            $this->audit->record($admin, 'support.ticket.assigned', $ticket, ['assignee_id' => $assignee?->uuid]);

            return $ticket->load(['requester.organisation', 'requester.roles', 'assignee']);
        });
    }

    /**
     * The team's answer. The requester is told a reply is waiting, never what it says.
     */
    public function reply(User $admin, string $id, string $body): SupportMessage
    {
        $message = DB::transaction(function () use ($admin, $id, $body) {
            $ticket = $this->locked($id);

            if ($ticket->status === SupportStatus::Closed) {
                throw ApiException::unprocessable('TICKET_CLOSED');
            }

            $ticket->status = SupportStatus::Answered;
            $ticket->assignee_id ??= $admin->id;

            $message = $this->conversation->write($ticket, $admin, true, $body, null);

            $this->audit->record($admin, 'support.ticket.replied', $ticket);

            return $message;
        });

        $ticket = $message->ticket;

        $this->notifier->send(
            $ticket->requester,
            NotificationCategory::Support,
            __('support.reply.title'),
            __('support.reply.body'),
            ['target_id' => $ticket->uuid],
        );

        return $message;
    }

    public function close(User $admin, string $id): SupportTicket
    {
        return DB::transaction(function () use ($admin, $id) {
            $ticket = $this->locked($id);

            if ($ticket->status === SupportStatus::Closed) {
                throw ApiException::unprocessable('TICKET_CLOSED');
            }

            $ticket->fill(['status' => SupportStatus::Closed, 'closed_at' => now()]);
            $ticket->save();

            $this->audit->record($admin, 'support.ticket.closed', $ticket);

            return $ticket->load(['requester.organisation', 'requester.roles', 'assignee']);
        });
    }

    private function locked(string $id): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->where('uuid', $id)
            ->lockForUpdate()
            ->first();

        if ($ticket === null) {
            throw ApiException::notFound(SupportTicket::NOT_FOUND);
        }

        return $ticket;
    }
}

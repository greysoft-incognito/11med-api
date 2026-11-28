<?php

namespace App\Http\Resources\Support;

use App\Http\Resources\ApiResource;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

/**
 * `zSupportTicket`, as its requester reads it.
 *
 * @property SupportTicket $resource
 */
class SupportTicketResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $ticket = $this->resource;

        return [
            'id' => $ticket->uuid,
            'category' => $ticket->category->value,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'link' => $ticket->link_kind === null ? null : ['kind' => $ticket->link_kind->value, 'id' => $ticket->link_id],
            'created_at' => $this->iso($ticket->created_at),
            'last_activity_at' => $this->iso($ticket->last_activity_at),
            'closed_at' => $this->iso($ticket->closed_at),
        ];
    }
}

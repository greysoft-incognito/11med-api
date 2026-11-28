<?php

namespace App\Http\Resources\Support;

use App\Models\SupportTicket;
use Illuminate\Http\Request;

/**
 * `zAdminSupportTicket`: the ticket with who asked and who has it. Account data only.
 *
 * @property SupportTicket $resource
 */
class AdminSupportTicketResource extends SupportTicketResource
{
    public function toArray(Request $request): array
    {
        $requester = $this->resource->requester;
        $assignee = $this->resource->assignee;

        return [
            ...parent::toArray($request),
            'requester' => [
                'id' => $requester->uuid,
                'name' => $requester->full_name,
                'role' => $requester->role()?->value,
                'email' => $requester->email,
                'phone' => $requester->phone,
                'organisation_name' => $requester->organisation?->name,
            ],
            'assignee' => $assignee === null ? null : ['id' => $assignee->uuid, 'name' => $assignee->full_name],
        ];
    }
}

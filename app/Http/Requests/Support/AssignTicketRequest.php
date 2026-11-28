<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\ApiRequest;

/**
 * `zAssignSupportTicketRequest`: a team member's id, or null to unassign.
 */
class AssignTicketRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'assignee_id' => ['present', 'nullable', 'uuid'],
        ];
    }
}

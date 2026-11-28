<?php

namespace App\Http\Requests\Staff;

/**
 * `zInviteStaffRequest`: who to invite, and the access they start with.
 */
class InviteStaffRequest extends StaffAccessRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:191'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            ...parent::rules(),
        ];
    }
}

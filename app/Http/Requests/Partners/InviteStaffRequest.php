<?php

namespace App\Http\Requests\Partners;

use App\Http\Requests\ApiRequest;

/**
 * `zInviteStaffRequest`.
 */
class InviteStaffRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Identity;

use App\Enums\Role;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zLinkPhoneRequest`.
 */
class LinkPhoneRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'link_token' => ['required', 'string', 'max:128'],
            'phone_number' => IdentityRules::phone(),
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'role' => ['required', Rule::in(Role::values(Role::app()))],
            ...IdentityRules::consent(),
        ];
    }
}

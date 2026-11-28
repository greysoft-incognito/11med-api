<?php

namespace App\Http\Requests\Identity;

use App\Enums\AccountKind;
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
            'role' => ['required', Rule::in(AccountKind::values(AccountKind::app()))],
            ...IdentityRules::consent(),
        ];
    }
}

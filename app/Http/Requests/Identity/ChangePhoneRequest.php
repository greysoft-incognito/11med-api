<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zChangePhoneRequest`.
 */
class ChangePhoneRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone_number' => IdentityRules::phone(),
            'password' => ['required', 'string', 'max:128'],
        ];
    }
}

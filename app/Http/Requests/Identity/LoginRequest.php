<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zLoginRequest`.
 */
class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone_number' => IdentityRules::phone(),
            'password' => ['required', 'string', 'max:128'],
            'device_id' => ['required', 'string', 'max:64'],
        ];
    }
}

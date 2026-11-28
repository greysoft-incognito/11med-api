<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zDisableTwoFactorRequest`: the password and a code, from the authenticator or a recovery code.
 */
class DisableTwoFactorRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:32'],
        ];
    }
}

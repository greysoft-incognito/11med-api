<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zResetPasswordRequest`.
 */
class ResetPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone_number' => IdentityRules::phone(),
            'code' => IdentityRules::code(),
            'password' => IdentityRules::password(),
        ];
    }
}

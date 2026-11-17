<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zChangePasswordRequest`.
 */
class ChangePasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128'],
            'new_password' => IdentityRules::password(),
        ];
    }
}

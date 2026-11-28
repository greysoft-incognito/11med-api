<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zTwoFactorConfirmRequest`: the authenticator's first code.
 */
class TwoFactorConfirmRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => IdentityRules::code(),
        ];
    }
}

<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zVerifyEmailRequest`.
 */
class VerifyEmailRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => IdentityRules::code(),
        ];
    }
}

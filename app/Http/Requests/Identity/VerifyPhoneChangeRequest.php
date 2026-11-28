<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zVerifyPhoneChangeRequest`: the code sent to the new number.
 */
class VerifyPhoneChangeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => IdentityRules::code(),
        ];
    }
}

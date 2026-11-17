<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zVerifyOtpRequest`.
 */
class VerifyOtpRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone_number' => IdentityRules::phone(),
            'code' => IdentityRules::code(),
        ];
    }
}

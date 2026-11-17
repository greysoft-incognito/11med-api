<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * A body of one phone number: resend-otp and `zForgotPasswordRequest`.
 */
class PhoneRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone_number' => IdentityRules::phone(),
        ];
    }
}

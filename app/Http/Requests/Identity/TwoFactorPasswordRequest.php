<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zTwoFactorPasswordRequest`: starting setup or new recovery codes.
 */
class TwoFactorPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:128'],
        ];
    }
}

<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zSetPasswordRequest` (portals): eight characters with a letter and a digit.
 */
class SetPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'regex:/[A-Za-z]/', 'regex:/\d/'],
        ];
    }
}

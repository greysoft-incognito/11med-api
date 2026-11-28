<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zChangePortalPasswordRequest`: the portals' password rule, eight characters with a letter and a digit.
 */
class ChangePortalPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128'],
            'new_password' => ['required', 'string', 'min:8', 'max:128', 'regex:/[A-Za-z]/', 'regex:/\d/'],
        ];
    }
}

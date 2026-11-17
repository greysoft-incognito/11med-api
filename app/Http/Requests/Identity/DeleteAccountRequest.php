<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zDeleteAccountRequest`.
 */
class DeleteAccountRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:128'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}

<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zRefreshRequest`, for both clients.
 */
class RefreshRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string', 'max:128'],
        ];
    }
}

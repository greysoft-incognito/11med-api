<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

class LogoutRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'refresh_token' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }
}

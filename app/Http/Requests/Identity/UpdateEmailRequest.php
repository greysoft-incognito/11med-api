<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zUpdateEmailRequest`: an address, or null to remove it.
 */
class UpdateEmailRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['present', 'nullable', 'string', 'email', 'max:254'],
        ];
    }
}

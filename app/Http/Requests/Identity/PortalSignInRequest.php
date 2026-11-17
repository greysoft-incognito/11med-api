<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zSignInRequest` (portals `src/api/contracts/auth.ts`).
 */
class PortalSignInRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:128'],
        ];
    }
}

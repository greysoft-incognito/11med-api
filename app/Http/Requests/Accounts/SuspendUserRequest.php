<?php

namespace App\Http\Requests\Accounts;

use App\Http\Requests\ApiRequest;

/**
 * `zSuspendUserRequest` (portal `src/api/contracts/operations.ts`).
 */
class SuspendUserRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:12', 'max:500'],
        ];
    }
}

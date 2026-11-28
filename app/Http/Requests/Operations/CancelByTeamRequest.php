<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\ApiRequest;

/**
 * `zTeamCancelRequest` (portal `src/api/contracts/operations.ts`): why the team cancelled, which the
 * patient reads on an order.
 */
class CancelByTeamRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:12', 'max:300'],
        ];
    }
}

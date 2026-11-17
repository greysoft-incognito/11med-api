<?php

namespace App\Http\Requests\Caregiving;

use App\Http\Requests\ApiRequest;

/**
 * `zAcceptInviteRequest`.
 */
class AcceptInviteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ];
    }
}

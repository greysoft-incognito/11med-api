<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zAcceptPolicyRequest`: the version the user read and accepted.
 */
class AcceptPolicyRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'policy_version' => ['required', 'string', 'max:32'],
        ];
    }
}

<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\ApiRequest;

/**
 * `zTwoFactorChallengeRequest`: the sign-in's challenge token and a code.
 */
class TwoFactorChallengeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:32'],
        ];
    }
}

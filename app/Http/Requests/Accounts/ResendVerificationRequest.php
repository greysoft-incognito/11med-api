<?php

namespace App\Http\Requests\Accounts;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zResendVerificationRequest` (portal `src/api/contracts/operations.ts`).
 */
class ResendVerificationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(['phone', 'email'])],
        ];
    }
}

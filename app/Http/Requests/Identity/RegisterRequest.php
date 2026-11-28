<?php

namespace App\Http\Requests\Identity;

use App\Enums\AccountKind;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zRegisterRequest` (app `src/api/contracts/auth.ts`).
 */
class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone_number' => IdentityRules::phone(),
            'password' => IdentityRules::password(),
            'role' => ['required', Rule::in(AccountKind::values(AccountKind::app()))],
            'device_id' => ['required', 'string', 'max:64'],
            'fcm_token' => ['sometimes', 'string'],
            ...IdentityRules::consent(),
        ];
    }
}

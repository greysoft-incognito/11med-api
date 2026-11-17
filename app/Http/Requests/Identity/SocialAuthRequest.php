<?php

namespace App\Http\Requests\Identity;

use App\Enums\SocialProvider;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zSocialAuthRequest`.
 */
class SocialAuthRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(SocialProvider::class)],
            'id_token' => ['required', 'string', 'max:8192'],
            'device_id' => ['required', 'string', 'max:64'],
            'full_name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:254'],
        ];
    }
}

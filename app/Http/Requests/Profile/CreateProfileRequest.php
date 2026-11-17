<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

/**
 * `zCreateProfileRequest`: the required core collected at onboarding.
 */
class CreateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return ProfileRules::core(partial: false);
    }
}

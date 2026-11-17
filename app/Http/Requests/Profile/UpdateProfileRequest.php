<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

/**
 * `zUpdateProfileRequest`: any field, absent ones left alone.
 */
class UpdateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            ...ProfileRules::core(partial: true),
            ...ProfileRules::progressive(),
        ];
    }
}

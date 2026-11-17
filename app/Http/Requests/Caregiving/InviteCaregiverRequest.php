<?php

namespace App\Http\Requests\Caregiving;

use App\Http\Requests\ApiRequest;
use App\Http\Requests\Identity\IdentityRules;

/**
 * `zInviteCaregiverRequest`.
 */
class InviteCaregiverRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone' => IdentityRules::phone(),
        ];
    }
}

<?php

namespace App\Http\Requests\Staff;

use App\Enums\AdminPermission;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zStaffAccessRequest`: the staff roles and direct permissions a team member holds.
 */
class StaffAccessRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => ['uuid', 'distinct'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(AdminPermission::values())],
        ];
    }
}

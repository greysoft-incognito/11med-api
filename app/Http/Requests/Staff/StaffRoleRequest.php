<?php

namespace App\Http\Requests\Staff;

use App\Enums\AdminPermission;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zStaffRoleRequest`: a role's name, what it is for, and its permissions.
 */
class StaffRoleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(AdminPermission::values())],
        ];
    }
}

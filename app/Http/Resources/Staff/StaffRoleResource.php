<?php

namespace App\Http\Resources\Staff;

use App\Enums\AdminPermission;
use App\Http\Resources\ApiResource;
use App\Models\StaffRole;
use Illuminate\Http\Request;

/**
 * `zStaffRole`. The super admin role is `protected`: it holds every permission and is never edited.
 *
 * @property StaffRole $resource
 */
class StaffRoleResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $role = $this->resource;

        return [
            'id' => $role->uuid,
            'name' => $role->label,
            'description' => $role->description,
            'protected' => $role->isSuperAdmin(),
            'permissions' => array_values(array_intersect(AdminPermission::values(), $role->permissions->pluck('name')->all())),
            'staff_count' => (int) ($role->users_count ?? 0),
        ];
    }
}

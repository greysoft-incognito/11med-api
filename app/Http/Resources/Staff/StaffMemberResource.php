<?php

namespace App\Http\Resources\Staff;

use App\Http\Resources\ApiResource;
use App\Models\User;
use App\Services\Staff\StaffAccess;
use Illuminate\Http\Request;

/**
 * `zStaffMember`. `invited` until the first password is set; `permissions` are the direct ones,
 * `effective_permissions` the union with the roles'.
 *
 * @property User $resource
 */
class StaffMemberResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $member = $this->resource;
        $roles = $member->roles->where('is_staff', true);
        $effective = app(StaffAccess::class)->permissionsOf($member);
        $status = match (true) {
            $member->deactivated_at !== null => 'deactivated',
            $member->password === null => 'invited',
            default => 'active',
        };

        return [
            'id' => $member->uuid,
            'email' => $member->email,
            'name' => $member->full_name,
            'status' => $status,
            'roles' => $roles
                ->map(fn($role) => ['id' => $role->uuid, 'name' => $role->label])
                ->values()
                ->all(),
            'permissions' => $member->permissions->pluck('name')->values()->all(),
            'effective_permissions' => $effective,
            'created_at' => $this->iso($member->created_at),
            'deactivated_at' => $this->iso($member->deactivated_at),
        ];
    }
}

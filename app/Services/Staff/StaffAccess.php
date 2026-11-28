<?php

namespace App\Services\Staff;

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a team account may do: the union of its direct permissions and its staff roles' permissions.
 * Deactivated accounts hold nothing.
 */
class StaffAccess implements PermissionHolders
{
    public function holders(AdminPermission $permission): Builder
    {
        return User::query()
            ->role(Role::Admin->value)
            ->whereNull('deactivated_at')
            ->permission($permission->value);
    }

    /**
     * The account's effective admin permissions, in catalogue order.
     *
     * @return list<string>
     */
    public function permissionsOf(User $user): array
    {
        if ($user->role() !== Role::Admin || $user->deactivated_at !== null) {
            return [];
        }

        $held = $user
            ->getAllPermissions()
            ->pluck('name')
            ->all();

        return array_values(array_intersect(AdminPermission::values(), $held));
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(StaffRole::superAdminName());
    }

    /**
     * Active super admins other than the given account.
     */
    public function otherSuperAdmins(User $except): int
    {
        return User::query()
            ->role(StaffRole::superAdminName())
            ->whereNull('deactivated_at')
            ->whereKeyNot($except->id)
            ->count();
    }

    /**
     * Refuses when the actor would give or take away an admin permission they do not hold themselves.
     * The kit's platform permissions travel only with the super admin role.
     *
     * @param  iterable<string>  $permissions
     */
    public function assertHolds(User $actor, iterable $permissions, string $field = 'permissions'): void
    {
        $held = $this->permissionsOf($actor);

        foreach ($permissions as $permission) {
            if (AdminPermission::tryFrom($permission) !== null && ! in_array($permission, $held, true)) {
                throw ApiException::unprocessable('PERMISSION_NOT_HELD', $field);
            }
        }
    }
}

<?php

namespace Tests\Concerns;

use App\Enums\AdminPermission;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Team accounts holding chosen admin permissions, every one by default.
 */
trait MakesAdmins
{
    protected function teamMember(AdminPermission ...$permissions): User
    {
        $permissions = $permissions === [] ? AdminPermission::cases() : $permissions;
        $names = AdminPermission::values($permissions);

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = User::factory()
            ->staff()
            ->create();

        $admin->givePermissionTo($names);

        return $admin;
    }
}

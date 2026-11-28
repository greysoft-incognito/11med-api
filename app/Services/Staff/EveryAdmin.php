<?php

namespace App\Services\Staff;

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stand-in until staff roles land: every team account holds every permission.
 */
class EveryAdmin implements PermissionHolders
{
    public function holders(AdminPermission $permission): Builder
    {
        return User::query()->role(Role::Admin->value);
    }
}

<?php

namespace App\Services\Staff;

use App\Enums\AdminPermission;
use App\Models\User;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Database\Eloquent\Builder;

/**
 * The team accounts that hold a permission, directly or through a staff role.
 */
#[Bind(StaffAccess::class)]
interface PermissionHolders
{
    /**
     * Active team accounts holding the permission, as a query to page or chunk.
     *
     * @return Builder<User>
     */
    public function holders(AdminPermission $permission): Builder;
}

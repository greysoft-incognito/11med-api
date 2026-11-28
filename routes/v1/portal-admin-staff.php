<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Staff\StaffRoleController;
use Illuminate\Support\Facades\Route;

// The team's accounts, the roles that group permissions, and the permission catalogue.
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        Route::get('permissions', [StaffRoleController::class, 'permissions'])
            ->name('permissions')
            ->middleware(AdminPermission::middleware(AdminPermission::RolesView, AdminPermission::StaffView));

        Route::get('roles', [StaffRoleController::class, 'index'])
            ->name('roles.index')
            ->middleware(AdminPermission::middleware(AdminPermission::RolesView, AdminPermission::StaffView));
        Route::post('roles', [StaffRoleController::class, 'store'])
            ->name('roles.store')
            ->middleware(AdminPermission::middleware(AdminPermission::RolesManage));
        Route::put('roles/{id}', [StaffRoleController::class, 'update'])
            ->whereUuid('id')
            ->name('roles.update')
            ->middleware(AdminPermission::middleware(AdminPermission::RolesManage));
        Route::delete('roles/{id}', [StaffRoleController::class, 'destroy'])
            ->whereUuid('id')
            ->name('roles.destroy')
            ->middleware(AdminPermission::middleware(AdminPermission::RolesManage));
    });

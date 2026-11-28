<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffRoleController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The team's accounts, the roles that group permissions, and the permission catalogue.
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
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

        Route::get('staff', [StaffController::class, 'index'])
            ->name('staff.index')
            ->middleware(AdminPermission::middleware(AdminPermission::StaffView));
        Route::post('staff', [StaffController::class, 'store'])
            ->name('staff.store')
            ->middleware(AdminPermission::middleware(AdminPermission::StaffManage));
        Route::get('staff/{id}', [StaffController::class, 'show'])
            ->whereUuid('id')
            ->name('staff.show')
            ->middleware(AdminPermission::middleware(AdminPermission::StaffView));
        Route::put('staff/{id}/access', [StaffController::class, 'access'])
            ->whereUuid('id')
            ->name('staff.access')
            ->middleware(AdminPermission::middleware(AdminPermission::StaffManage));
        Route::post('staff/{id}/deactivate', [StaffController::class, 'deactivate'])
            ->whereUuid('id')
            ->name('staff.deactivate')
            ->middleware(AdminPermission::middleware(AdminPermission::StaffManage));
        Route::post('staff/{id}/reactivate', [StaffController::class, 'reactivate'])
            ->whereUuid('id')
            ->name('staff.reactivate')
            ->middleware(AdminPermission::middleware(AdminPermission::StaffManage));
    });

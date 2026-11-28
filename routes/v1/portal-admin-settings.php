<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Settings\AdminSettingController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The values the team changes at runtime: free-tier numbers, commission, code and sign-in limits.
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
    ->prefix('portal/admin/settings')
    ->name('portal.admin.settings.')
    ->group(function () {
        Route::get('/', [AdminSettingController::class, 'index'])
            ->middleware(AdminPermission::middleware(AdminPermission::SettingsView))
            ->name('index');
        Route::put('{key}', [AdminSettingController::class, 'update'])
            ->middleware(AdminPermission::middleware(AdminPermission::SettingsEdit))
            ->name('update');
    });

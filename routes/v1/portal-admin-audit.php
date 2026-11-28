<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Audit\AuditController;
use Illuminate\Support\Facades\Route;

// What the team did, newest first.
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        Route::get('audit', [AuditController::class, 'index'])
            ->name('audit.index')
            ->middleware(AdminPermission::middleware(AdminPermission::AuditView));
    });

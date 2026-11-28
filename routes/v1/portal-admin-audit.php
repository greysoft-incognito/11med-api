<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Audit\AuditController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// What the team did, newest first.
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        Route::get('audit', [AuditController::class, 'index'])
            ->name('audit.index')
            ->middleware(AdminPermission::middleware(AdminPermission::AuditView));
    });

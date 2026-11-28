<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Desk\AdminSummaryController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The admin portal's home (portal handover §6).
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        Route::get('summary', [AdminSummaryController::class, 'show'])
            ->name('summary')
            ->middleware(AdminPermission::middleware(AdminPermission::SummaryView));
    });

<?php

use App\Enums\Role;
use App\Http\Controllers\Desk\AdminSummaryController;
use Illuminate\Support\Facades\Route;

// The admin portal's home (portal handover §6).
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        Route::get('summary', [AdminSummaryController::class, 'show'])->name('summary');
    });

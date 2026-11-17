<?php

use App\Enums\Role;
use App\Http\Controllers\Subscriptions\AdminSubscriptionController;
use Illuminate\Support\Facades\Route;

// Premium bought outside the app, recorded by the team (portal handover §6 "Subscriptions").
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/subscriptions')
    ->name('portal.admin.subscriptions.')
    ->group(function () {
        Route::get('/', [AdminSubscriptionController::class, 'index'])->name('index');
        Route::post('/', [AdminSubscriptionController::class, 'store'])->name('store');
    });

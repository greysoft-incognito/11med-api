<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Subscriptions\AdminSubscriptionController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Premium bought outside the app, recorded by the team (portal handover §6 "Subscriptions").
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
    ->prefix('portal/admin/subscriptions')
    ->name('portal.admin.subscriptions.')
    ->group(function () {
        Route::get('/', [AdminSubscriptionController::class, 'index'])
            ->name('index')
            ->middleware(AdminPermission::middleware(AdminPermission::SubscriptionsView));
        Route::post('/', [AdminSubscriptionController::class, 'store'])
            ->name('store')
            ->middleware(AdminPermission::middleware(AdminPermission::SubscriptionsRecord));
    });

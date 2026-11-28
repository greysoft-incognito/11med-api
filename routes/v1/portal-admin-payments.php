<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Payments\AdminPaymentController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        Route::get('payments', [AdminPaymentController::class, 'index'])
            ->name('payments.index')
            ->middleware(AdminPermission::middleware(AdminPermission::PaymentsView));
    });

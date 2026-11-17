<?php

use App\Enums\Role;
use App\Http\Controllers\Payments\AdminPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    });

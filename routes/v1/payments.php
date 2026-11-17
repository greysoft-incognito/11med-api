<?php

use App\Enums\Role;
use App\Http\Controllers\Payments\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Patient->value])
    ->prefix('payments')
    ->name('payments.')
    ->group(function () {
        Route::post('/', [PaymentController::class, 'store'])->middleware('throttle:payments')->name('store');
        Route::get('{reference}', [PaymentController::class, 'show'])->name('show');
    });

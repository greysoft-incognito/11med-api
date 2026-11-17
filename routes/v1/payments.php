<?php

use App\Enums\Role;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

// Paystack's webhook: server to server, authenticated by its signature (FE-005).
Route::post('payments/webhook/paystack', PaystackWebhookController::class)->name('payments.webhook.paystack');

Route::middleware(['auth:sanctum', 'role:' . Role::Patient->value])
    ->prefix('payments')
    ->name('payments.')
    ->group(function () {
        Route::post('/', [PaymentController::class, 'store'])->middleware('throttle:payments')->name('store');
        Route::get('{reference}', [PaymentController::class, 'show'])->name('show');
    });

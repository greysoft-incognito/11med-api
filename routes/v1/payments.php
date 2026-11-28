<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\PaystackWebhookController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Paystack's webhook: server to server, authenticated by its signature (FE-005).
Route::post('payments/webhook/paystack', PaystackWebhookController::class)->name('payments.webhook.paystack');

Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Patient])])
    ->prefix('payments')
    ->name('payments.')
    ->group(function () {
        Route::post('/', [PaymentController::class, 'store'])->middleware('throttle:payments')->name('store');
        Route::get('{reference}', [PaymentController::class, 'show'])->name('show');
    });

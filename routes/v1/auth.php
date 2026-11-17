<?php

use App\Enums\Role;
use App\Http\Controllers\Identity\AppAuthController;
use Illuminate\Support\Facades\Route;

$app = 'role:' . implode('|', Role::values(Role::app()));

Route::prefix('auth')->name('auth.')->group(function () use ($app) {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AppAuthController::class, 'register'])->name('register');
        Route::post('verify-otp', [AppAuthController::class, 'verifyOtp'])->name('verify-otp');
        Route::post('resend-otp', [AppAuthController::class, 'resendOtp'])->name('resend-otp');
        Route::post('login', [AppAuthController::class, 'login'])->name('login');
        Route::post('refresh', [AppAuthController::class, 'refresh'])->name('refresh');
        Route::post('logout', [AppAuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth:sanctum', $app])->group(function () {
        Route::get('session', [AppAuthController::class, 'session'])->name('session');
    });
});

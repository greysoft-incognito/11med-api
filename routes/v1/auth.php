<?php

use App\Enums\Role;
use App\Http\Controllers\Identity\AppAuthController;
use App\Http\Controllers\Identity\AppEmailController;
use App\Http\Controllers\Identity\AppPasswordController;
use App\Http\Controllers\Identity\SocialAuthController;
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
        Route::post('forgot-password', [AppPasswordController::class, 'forgot'])->name('forgot-password');
        Route::post('reset-password', [AppPasswordController::class, 'reset'])->name('reset-password');
        Route::post('social', [SocialAuthController::class, 'signIn'])->name('social');
        Route::post('social/phone', [SocialAuthController::class, 'linkPhone'])->name('social.phone');
    });

    Route::middleware(['auth:sanctum', $app])->group(function () {
        Route::get('session', [AppAuthController::class, 'session'])->name('session');
        Route::post('change-password', [AppPasswordController::class, 'change'])->name('change-password');
        Route::patch('email', [AppEmailController::class, 'update'])->name('email.update');
        Route::post('email/verify', [AppEmailController::class, 'verify'])->name('email.verify');
        Route::post('email/resend', [AppEmailController::class, 'resend'])->name('email.resend');
    });
});

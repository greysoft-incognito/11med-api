<?php

use App\Enums\Role;
use App\Http\Controllers\Identity\PortalAccountController;
use App\Http\Controllers\Identity\SessionController;
use App\Http\Controllers\Identity\TwoFactorController;
use Illuminate\Support\Facades\Route;

// A portal account's own page: name, email, password, signed-in devices, two-step verification (M14).
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::portal()))])
    ->prefix('portal/account')
    ->name('portal.account.')
    ->group(function () {
        Route::patch('/', [PortalAccountController::class, 'update'])->name('update');
        Route::post('email', [PortalAccountController::class, 'email'])->middleware('throttle:auth')->name('email');
        Route::post('email/verify', [PortalAccountController::class, 'verifyEmail'])->middleware('throttle:auth')->name('email.verify');
        Route::post('password', [PortalAccountController::class, 'password'])->middleware('throttle:auth')->name('password');

        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::delete('sessions', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
        Route::delete('sessions/{sessionId}', [SessionController::class, 'destroy'])->whereUuid('sessionId')->name('sessions.destroy');

        Route::get('two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('two-factor', [TwoFactorController::class, 'store'])->name('two-factor.store');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('two-factor', [TwoFactorController::class, 'destroy'])->name('two-factor.destroy');
        Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    });

Route::middleware('throttle:portal-auth')
    ->post('portal/auth/two-factor', [TwoFactorController::class, 'verifyPortal'])
    ->name('portal.auth.two-factor');

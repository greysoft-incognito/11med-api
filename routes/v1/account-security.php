<?php

use App\Enums\Role;
use App\Http\Controllers\Identity\SessionController;
use App\Http\Controllers\Identity\TwoFactorController;
use Illuminate\Support\Facades\Route;

// The app account's security: signed-in devices and two-step verification (M14).
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::app()))])
    ->prefix('auth')
    ->name('auth.')
    ->group(function () {
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::delete('sessions', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
        Route::delete('sessions/{sessionId}', [SessionController::class, 'destroy'])->whereUuid('sessionId')->name('sessions.destroy');

        Route::get('two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('two-factor', [TwoFactorController::class, 'store'])->name('two-factor.store');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('two-factor', [TwoFactorController::class, 'destroy'])->name('two-factor.destroy');
        Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    });

Route::middleware('throttle:auth')
    ->post('auth/two-factor/verify', [TwoFactorController::class, 'verifyApp'])
    ->name('auth.two-factor.verify');

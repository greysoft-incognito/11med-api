<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Identity\AppPhoneController;
use App\Http\Controllers\Identity\DataExportController;
use App\Http\Controllers\Identity\SessionController;
use App\Http\Controllers\Identity\TwoFactorController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The app account's security: signed-in devices, two-step verification, the phone number, a copy of their data (M14).
Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::app())])
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

        Route::post('phone', [AppPhoneController::class, 'update'])->middleware('throttle:auth')->name('phone.update');
        Route::post('phone/verify', [AppPhoneController::class, 'verify'])->middleware('throttle:auth')->name('phone.verify');

        Route::get('data-export', [DataExportController::class, 'show'])->name('data-export.show');
        Route::post('data-export', [DataExportController::class, 'store'])->name('data-export.store');
    });

// A data export's zip, behind a signed link.
Route::get('data-exports/{export}/file', [DataExportController::class, 'file'])
    ->middleware('signed')
    ->whereUuid('export')
    ->name('data-exports.file');

Route::middleware('throttle:auth')
    ->post('auth/two-factor/verify', [TwoFactorController::class, 'verifyApp'])
    ->name('auth.two-factor.verify');

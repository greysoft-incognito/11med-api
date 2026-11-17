<?php

use App\Enums\Role;
use App\Http\Controllers\Identity\PortalAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/auth')->name('portal.auth.')->group(function () {
    Route::middleware('throttle:portal-auth')->group(function () {
        Route::post('sign-in', [PortalAuthController::class, 'signIn'])->name('sign-in');
        Route::post('forgot', [PortalAuthController::class, 'forgot'])->name('forgot');
    });

    Route::middleware('throttle:auth')->group(function () {
        Route::post('refresh', [PortalAuthController::class, 'refresh'])->name('refresh');
        Route::post('sign-out', [PortalAuthController::class, 'signOut'])->name('sign-out');
        Route::post('set-password', [PortalAuthController::class, 'setPassword'])->name('set-password');
    });

    Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::portal()))])
        ->get('session', [PortalAuthController::class, 'session'])
        ->name('session');
});

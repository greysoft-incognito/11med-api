<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Identity\PolicyConsentController;
use App\Http\Controllers\Identity\PortalAuthController;
use App\Http\Middleware\RequireAccountKind;
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

    Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::portal(), staff: true)])
        ->get('session', [PortalAuthController::class, 'session'])
        ->name('session');

    Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::portal())])
        ->post('consent', [PolicyConsentController::class, 'portal'])
        ->name('consent');
});

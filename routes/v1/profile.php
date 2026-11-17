<?php

use App\Enums\Role;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\QrController;
use Illuminate\Support\Facades\Route;

// The emergency page a QR code opens: public, rate limited by token and by address.
Route::get('profile/view', [QrController::class, 'view'])->middleware('throttle:emergency')->name('profile.view');

Route::middleware(['auth:sanctum', 'role:' . Role::Patient->value])
    ->prefix('profile')
    ->name('profile.')
    ->group(function () {
        Route::post('/', [ProfileController::class, 'store'])->name('store');
        Route::post('qr/revoke', [QrController::class, 'revoke'])->name('qr.revoke');
        Route::get('{userId}', [ProfileController::class, 'show'])->whereUuid('userId')->name('show');
        Route::patch('{userId}', [ProfileController::class, 'update'])->whereUuid('userId')->name('update');
        Route::get('{userId}/qr', [QrController::class, 'show'])->whereUuid('userId')->name('qr');
    });

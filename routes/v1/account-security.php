<?php

use App\Enums\Role;
use App\Http\Controllers\Identity\SessionController;
use Illuminate\Support\Facades\Route;

// The app account's security: signed-in devices (M14).
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::app()))])
    ->prefix('auth')
    ->name('auth.')
    ->group(function () {
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::delete('sessions', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
        Route::delete('sessions/{sessionId}', [SessionController::class, 'destroy'])->whereUuid('sessionId')->name('sessions.destroy');
    });

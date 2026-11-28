<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// A user's own notification history and delivery choices (app spec §9); another account's id is 404.
Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::app())])
    ->prefix('notifications/{userId}')
    ->whereUuid('userId')
    ->name('notifications.')
    ->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::patch('read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::patch('read/{notificationId}', [NotificationController::class, 'read'])->whereUuid('notificationId')->name('read');
        Route::get('preferences', [NotificationController::class, 'preferences'])->name('preferences');
        Route::patch('preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
    });

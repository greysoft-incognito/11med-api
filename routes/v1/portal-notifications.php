<?php

use App\Enums\Role;
use App\Http\Controllers\Notifications\PortalNotificationController;
use Illuminate\Support\Facades\Route;

// A portal account's own notification centre (portal handover §8): history, the bell's count, and preferences.
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::portal()))])
    ->prefix('portal/notifications')
    ->name('portal.notifications.')
    ->group(function () {
        Route::get('/', [PortalNotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [PortalNotificationController::class, 'unreadCount'])->name('unread-count');
        Route::patch('read-all', [PortalNotificationController::class, 'readAll'])->name('read-all');
        Route::patch('{notificationId}/read', [PortalNotificationController::class, 'read'])->whereUuid('notificationId')->name('read');
        Route::get('preferences', [PortalNotificationController::class, 'preferences'])->name('preferences');
        Route::patch('preferences', [PortalNotificationController::class, 'updatePreferences'])->name('preferences.update');
    });

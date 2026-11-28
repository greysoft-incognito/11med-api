<?php

use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The kit's platform administration, for the team only.
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])->prefix('admin')->group(function () {
    Route::apiResource('users', UserController::class);

    Route::apiResource('configurations', ConfigurationController::class)->only(['index', 'show', 'store']);

    // Notifications Templates
    Route::apiResource('configurations/notifications/templates', NotificationTemplateController::class)
        ->except(['store']);
});

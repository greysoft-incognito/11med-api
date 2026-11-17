<?php

use App\Enums\Role;
use App\Http\Controllers\Subscriptions\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Read-only: nothing is sold in the app (app spec §7.3).
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::app()))])
    ->get('subscriptions/{userId}', [SubscriptionController::class, 'show'])
    ->whereUuid('userId')
    ->name('subscriptions.show');

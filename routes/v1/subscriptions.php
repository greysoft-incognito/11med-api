<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Subscriptions\SubscriptionController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Read-only: nothing is sold in the app (app spec §7.3).
Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::app())])
    ->get('subscriptions/{userId}', [SubscriptionController::class, 'show'])
    ->whereUuid('userId')
    ->name('subscriptions.show');

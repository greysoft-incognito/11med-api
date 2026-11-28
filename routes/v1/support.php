<?php

use App\Enums\Role;
use App\Http\Controllers\Support\SupportController;
use Illuminate\Support\Facades\Route;

// Contact support (M18): app accounts and partner portal accounts, each seeing only their own tickets.
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values([Role::Patient, Role::Doctor, Role::Pharmacy, Role::Laboratory]))])
    ->prefix('support/tickets')
    ->name('support.tickets.')
    ->group(function () {
        Route::get('/', [SupportController::class, 'index'])->name('index');
        Route::post('/', [SupportController::class, 'store'])->middleware('throttle:support')->name('store');
        Route::get('{id}', [SupportController::class, 'show'])->whereUuid('id')->name('show');
        Route::get('{id}/messages', [SupportController::class, 'messages'])->whereUuid('id')->name('messages.index');
        Route::post('{id}/messages', [SupportController::class, 'reply'])->whereUuid('id')->middleware('throttle:support')->name('messages.store');
    });

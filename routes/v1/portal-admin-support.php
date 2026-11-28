<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Support\AdminSupportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/support/tickets')
    ->name('portal.admin.support.')
    ->group(function () {
        Route::middleware(AdminPermission::middleware(AdminPermission::SupportView, AdminPermission::SupportReply))->group(function () {
            Route::get('/', [AdminSupportController::class, 'index'])->name('index');
            Route::get('{id}', [AdminSupportController::class, 'show'])->whereUuid('id')->name('show');
            Route::get('{id}/messages', [AdminSupportController::class, 'messages'])->whereUuid('id')->name('messages.index');
        });

        Route::middleware(AdminPermission::middleware(AdminPermission::SupportReply))->group(function () {
            Route::post('{id}/assign', [AdminSupportController::class, 'assign'])->whereUuid('id')->name('assign');
            Route::post('{id}/messages', [AdminSupportController::class, 'reply'])->whereUuid('id')->name('messages.store');
            Route::post('{id}/close', [AdminSupportController::class, 'close'])->whereUuid('id')->name('close');
        });
    });

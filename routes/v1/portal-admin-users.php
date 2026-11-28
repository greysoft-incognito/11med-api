<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Accounts\AdminUserController;
use Illuminate\Support\Facades\Route;

// Patient and doctor accounts: the directory and what the team may do to an account.
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/users')
    ->name('portal.admin.users.')
    ->group(function () {
        Route::middleware(AdminPermission::middleware(AdminPermission::UsersView))->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('{id}', [AdminUserController::class, 'show'])->whereUuid('id')->name('show');
        });

        Route::middleware(AdminPermission::middleware(AdminPermission::UsersManage))->group(function () {
            Route::post('{id}/suspend', [AdminUserController::class, 'suspend'])->whereUuid('id')->name('suspend');
            Route::post('{id}/reinstate', [AdminUserController::class, 'reinstate'])->whereUuid('id')->name('reinstate');
            Route::post('{id}/sign-out', [AdminUserController::class, 'signOut'])->whereUuid('id')->name('sign-out');
            Route::post('{id}/verification', [AdminUserController::class, 'resendVerification'])->whereUuid('id')->name('verification');
            Route::post('{id}/password-reset', [AdminUserController::class, 'sendPasswordReset'])->whereUuid('id')->name('password-reset');
        });
    });

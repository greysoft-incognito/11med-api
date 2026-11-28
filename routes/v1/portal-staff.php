<?php

use App\Enums\Role;
use App\Http\Controllers\Partners\StaffController;
use Illuminate\Support\Facades\Route;

// A partner's own staff (M18): every member reads the list, the owner changes it.
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values([Role::Pharmacy, Role::Laboratory]))])
    ->prefix('portal/staff')
    ->name('portal.staff.')
    ->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::delete('{id}', [StaffController::class, 'destroy'])->whereUuid('id')->name('destroy');
        Route::post('{id}/owner', [StaffController::class, 'makeOwner'])->whereUuid('id')->name('owner');
    });

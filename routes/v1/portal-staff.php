<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Partners\StaffController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// A partner's own staff (M18): every member reads the list, the owner changes it.
Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::portal())])
    ->prefix('portal/staff')
    ->name('portal.staff.')
    ->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::delete('{id}', [StaffController::class, 'destroy'])->whereUuid('id')->name('destroy');
        Route::post('{id}/owner', [StaffController::class, 'makeOwner'])->whereUuid('id')->name('owner');
    });

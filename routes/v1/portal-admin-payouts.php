<?php

use App\Enums\Role;
use App\Http\Controllers\Consultations\PayoutDeskController;
use Illuminate\Support\Facades\Route;

// Doctors' payouts on the team's desk (portal handover §6 "Payouts").
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/payouts')
    ->name('portal.admin.payouts.')
    ->group(function () {
        Route::get('/', [PayoutDeskController::class, 'index'])->name('index');
        Route::post('{id}/settle', [PayoutDeskController::class, 'settle'])->whereUuid('id')->name('settle');
    });

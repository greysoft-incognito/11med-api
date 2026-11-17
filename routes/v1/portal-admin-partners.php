<?php

use App\Enums\Role;
use App\Http\Controllers\Partners\AdminPartnerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/partners')
    ->name('portal.admin.partners.')
    ->group(function () {
        Route::get('/', [AdminPartnerController::class, 'index'])->name('index');
        Route::post('{id}/review', [AdminPartnerController::class, 'review'])->whereUuid('id')->name('review');
    });

<?php

use App\Enums\Role;
use App\Http\Controllers\Laboratories\PortalLabBookingController;
use App\Http\Controllers\Laboratories\PortalLaboratoryController;
use Illuminate\Support\Facades\Route;

// The laboratory portal (portal handover §5), scoped to the account's own laboratory.
Route::middleware(['auth:sanctum', 'role:' . Role::Laboratory->value])
    ->prefix('portal/laboratory')
    ->name('portal.laboratory.')
    ->group(function () {
        Route::get('/', [PortalLaboratoryController::class, 'show'])->name('show');
        Route::patch('/', [PortalLaboratoryController::class, 'update'])->name('update');
        Route::get('summary', [PortalLaboratoryController::class, 'summary'])->name('summary');
        Route::get('catalogue', [PortalLaboratoryController::class, 'catalogue'])->name('catalogue');
        Route::get('tests', [PortalLaboratoryController::class, 'tests'])->name('tests.index');
        Route::put('tests/{code}', [PortalLaboratoryController::class, 'setTest'])->name('tests.set');
        Route::delete('tests/{code}', [PortalLaboratoryController::class, 'removeTest'])->name('tests.remove');
        Route::get('bookings', [PortalLabBookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{id}', [PortalLabBookingController::class, 'show'])->whereUuid('id')->name('bookings.show');
        Route::post('bookings/{id}/advance', [PortalLabBookingController::class, 'advance'])->whereUuid('id')->name('bookings.advance');
        Route::post('bookings/{id}/cancel', [PortalLabBookingController::class, 'cancel'])->whereUuid('id')->name('bookings.cancel');
    });

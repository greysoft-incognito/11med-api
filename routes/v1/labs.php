<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Laboratories\DirectoryController;
use App\Http\Controllers\Laboratories\LabBookingController;
use App\Http\Controllers\Laboratories\LabRequestController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Laboratories (app handover §10): the directory and the catalogue for patients and doctors,
// a patient's own requests and bookings.
Route::middleware('auth:sanctum')
    ->prefix('labs')
    ->name('labs.')
    ->group(function () {
        Route::middleware(RequireAccountKind::for(AccountKind::app()))->group(function () {
            Route::get('/', [DirectoryController::class, 'index'])->name('index');
            Route::get('tests', [DirectoryController::class, 'catalogue'])->name('catalogue');
        });

        Route::middleware(RequireAccountKind::for([AccountKind::Patient]))->group(function () {
            Route::get('requests', [LabRequestController::class, 'index'])->name('requests.index');
            Route::get('requests/{requestId}', [LabRequestController::class, 'show'])->whereUuid('requestId')->name('requests.show');
            Route::get('bookings', [LabBookingController::class, 'index'])->name('bookings.index');
            Route::post('bookings', [LabBookingController::class, 'store'])->middleware('throttle:lab-booking')->name('bookings.store');
            Route::get('bookings/{bookingId}', [LabBookingController::class, 'show'])->whereUuid('bookingId')->name('bookings.show');
            Route::post('bookings/{bookingId}/cancel', [LabBookingController::class, 'cancel'])->whereUuid('bookingId')->name('bookings.cancel');
        });

        Route::middleware(RequireAccountKind::for(AccountKind::app()))->group(function () {
            Route::get('{labId}', [DirectoryController::class, 'show'])->whereUuid('labId')->name('show');
            Route::get('{labId}/tests', [DirectoryController::class, 'tests'])->whereUuid('labId')->name('tests');
        });
    });

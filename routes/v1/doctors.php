<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Doctors\DoctorDirectoryController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The doctor directory and bookable slots (app spec §7.2, handover §13).
Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::app())])
    ->prefix('doctors')
    ->name('doctors.')
    ->group(function () {
        Route::get('/', [DoctorDirectoryController::class, 'index'])->name('index');
        Route::get('{doctorId}', [DoctorDirectoryController::class, 'show'])->whereUuid('doctorId')->name('show');
        Route::get('{doctorId}/slots', [DoctorDirectoryController::class, 'slots'])->whereUuid('doctorId')->name('slots');
    });

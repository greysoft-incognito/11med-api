<?php

use App\Enums\Role;
use App\Http\Controllers\Doctors\DoctorDirectoryController;
use Illuminate\Support\Facades\Route;

// The doctor directory and bookable slots (app spec §7.2, handover §13).
Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::app()))])
    ->prefix('doctors')
    ->name('doctors.')
    ->group(function () {
        Route::get('/', [DoctorDirectoryController::class, 'index'])->name('index');
        Route::get('{doctorId}', [DoctorDirectoryController::class, 'show'])->whereUuid('doctorId')->name('show');
        Route::get('{doctorId}/slots', [DoctorDirectoryController::class, 'slots'])->whereUuid('doctorId')->name('slots');
    });

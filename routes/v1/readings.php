<?php

use App\Enums\Role;
use App\Http\Controllers\Readings\ReadingController;
use Illuminate\Support\Facades\Route;

// Readings and insights (app handover §14): the patient's own.
Route::middleware(['auth:sanctum', 'role:' . Role::Patient->value])
    ->group(function () {
        Route::get('readings', [ReadingController::class, 'index'])->name('readings.index');
        Route::post('readings', [ReadingController::class, 'store'])->name('readings.store');
        Route::delete('readings/{id}', [ReadingController::class, 'destroy'])->whereUuid('id')->name('readings.destroy');
        Route::get('insights', [ReadingController::class, 'insights'])->name('insights');
    });

<?php

use App\Enums\Role;
use App\Http\Controllers\Medications\MedicationController;
use Illuminate\Support\Facades\Route;

// Schedules and doses (app spec §6). A caregiver reads a patient's schedule through the `{userId}` routes.
Route::middleware(['auth:sanctum', 'role:' . Role::Patient->value])
    ->prefix('medications')
    ->name('medications.')
    ->group(function () {
        Route::post('/', [MedicationController::class, 'store'])->name('store');
        Route::get('{userId}', [MedicationController::class, 'index'])->whereUuid('userId')->name('index');
        Route::patch('{medId}', [MedicationController::class, 'update'])->whereUuid('medId')->name('update');
        Route::delete('{medId}', [MedicationController::class, 'destroy'])->whereUuid('medId')->name('destroy');
        Route::post('{medId}/pause', [MedicationController::class, 'pause'])->whereUuid('medId')->name('pause');
        Route::post('{medId}/resume', [MedicationController::class, 'resume'])->whereUuid('medId')->name('resume');
    });

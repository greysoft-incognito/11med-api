<?php

use App\Enums\Role;
use App\Http\Controllers\Medications\DoseController;
use App\Http\Controllers\Medications\MedicationController;
use Illuminate\Support\Facades\Route;

// Schedules and doses (app spec §6). A caregiver reads a patient's schedule through the `{userId}` routes.
Route::middleware(['auth:sanctum', 'role:' . Role::Patient->value])
    ->prefix('medications')
    ->name('medications.')
    ->group(function () {
        Route::post('/', [MedicationController::class, 'store'])->name('store');
        Route::post('doses/log', [DoseController::class, 'log'])->name('doses.log');
        Route::post('doses/log-batch', [DoseController::class, 'logBatch'])->name('doses.log-batch');
        Route::delete('doses/log/{doseId}', [DoseController::class, 'retract'])->name('doses.retract');
        Route::get('{userId}', [MedicationController::class, 'index'])->whereUuid('userId')->name('index');
        Route::get('{userId}/doses', [DoseController::class, 'day'])->whereUuid('userId')->name('doses');
        Route::get('{userId}/history', [DoseController::class, 'history'])->whereUuid('userId')->name('history');
        Route::patch('{medId}', [MedicationController::class, 'update'])->whereUuid('medId')->name('update');
        Route::delete('{medId}', [MedicationController::class, 'destroy'])->whereUuid('medId')->name('destroy');
        Route::post('{medId}/pause', [MedicationController::class, 'pause'])->whereUuid('medId')->name('pause');
        Route::post('{medId}/resume', [MedicationController::class, 'resume'])->whereUuid('medId')->name('resume');
    });

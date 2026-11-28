<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Medications\ComplianceController;
use App\Http\Controllers\Medications\DoseController;
use App\Http\Controllers\Medications\MedicationController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The compliance report's PDF, behind a signed link that lapses in minutes (app handover §9).
Route::get('medications/reports/{report}/file', [ComplianceController::class, 'file'])
    ->middleware('signed')
    ->whereUuid('report')
    ->name('medications.report-file');

// Schedules and doses (app spec §6). A caregiver reads a patient's schedule through the `{userId}` routes.
Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Patient])])
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
        Route::get('{userId}/compliance', [ComplianceController::class, 'show'])->whereUuid('userId')->name('compliance');
        Route::get('{userId}/report', [ComplianceController::class, 'report'])->whereUuid('userId')->middleware('throttle:compliance-report')->name('report');
        Route::patch('{medId}', [MedicationController::class, 'update'])->whereUuid('medId')->name('update');
        Route::delete('{medId}', [MedicationController::class, 'destroy'])->whereUuid('medId')->name('destroy');
        Route::post('{medId}/pause', [MedicationController::class, 'pause'])->whereUuid('medId')->name('pause');
        Route::post('{medId}/resume', [MedicationController::class, 'resume'])->whereUuid('medId')->name('resume');
    });

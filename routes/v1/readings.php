<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Readings\ReadingController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Readings and insights (app handover §14): the patient's own.
Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Patient])])
    ->group(function () {
        Route::get('readings', [ReadingController::class, 'index'])->name('readings.index');
        Route::post('readings', [ReadingController::class, 'store'])->name('readings.store');
        Route::delete('readings/{id}', [ReadingController::class, 'destroy'])->whereUuid('id')->name('readings.destroy');
        Route::get('insights', [ReadingController::class, 'insights'])->name('insights');
    });

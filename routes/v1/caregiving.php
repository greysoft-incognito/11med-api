<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Caregiving\CaregiverController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Caregiving is a capability of a patient account, not a role (app spec §6.5).
Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Patient])])
    ->prefix('medications')
    ->name('caregivers.')
    ->group(function () {
        Route::get('caregivers', [CaregiverController::class, 'index'])->name('index');
        Route::post('caregivers/invite', [CaregiverController::class, 'invite'])->middleware('throttle:caregiver-invite')->name('invite');
        Route::post('caregivers/accept', [CaregiverController::class, 'accept'])->middleware('throttle:caregiver-accept')->name('accept');
        Route::delete('caregivers/{caregiverId}', [CaregiverController::class, 'destroy'])->whereUuid('caregiverId')->name('destroy');
        Route::get('caregiving', [CaregiverController::class, 'caringFor'])->name('caring-for');
    });

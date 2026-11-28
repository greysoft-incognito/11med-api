<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Consultations\ConsultationChatController;
use App\Http\Controllers\Consultations\ConsultationController;
use App\Http\Controllers\Consultations\PrescriptionController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The patient's consultations (app spec §7).
Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Patient])])
    ->prefix('consultations')
    ->name('consultations.')
    ->group(function () {
        Route::get('/', [ConsultationController::class, 'index'])->name('index');
        Route::post('/', [ConsultationController::class, 'store'])->name('store');
        Route::get('{id}', [ConsultationController::class, 'show'])->whereUuid('id')->name('show');
        Route::post('{id}/cancel', [ConsultationController::class, 'cancel'])->whereUuid('id')->name('cancel');
        Route::post('{id}/call', [ConsultationController::class, 'call'])->whereUuid('id')->name('call');
        Route::post('{id}/rate', [ConsultationController::class, 'rate'])->whereUuid('id')->name('rate');
        Route::get('{id}/messages', [ConsultationChatController::class, 'patientMessages'])->whereUuid('id')->name('messages');
        Route::post('{id}/messages', [ConsultationChatController::class, 'patientSend'])->whereUuid('id')->name('messages.send');
        Route::get('{id}/prescription', [PrescriptionController::class, 'show'])->whereUuid('id')->name('prescription');
        Route::post('{id}/prescription/apply', [PrescriptionController::class, 'apply'])->whereUuid('id')->name('prescription.apply');
    });

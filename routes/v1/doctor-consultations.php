<?php

use App\Enums\Role;
use App\Http\Controllers\Consultations\ConsultationChatController;
use App\Http\Controllers\Consultations\DoctorConsultationController;
use App\Http\Controllers\Consultations\PrescriptionController;
use Illuminate\Support\Facades\Route;

// The doctor's consultations and earnings (app spec §7.2, §7.6, §7.7).
Route::middleware(['auth:sanctum', 'role:' . Role::Doctor->value])
    ->prefix('doctor')
    ->name('doctor.')
    ->group(function () {
        Route::get('consultations', [DoctorConsultationController::class, 'index'])->name('consultations.index');
        Route::post('consultations/{id}/accept', [DoctorConsultationController::class, 'accept'])->whereUuid('id')->name('consultations.accept');
        Route::post('consultations/{id}/decline', [DoctorConsultationController::class, 'decline'])->whereUuid('id')->name('consultations.decline');
        Route::post('consultations/{id}/start', [DoctorConsultationController::class, 'start'])->whereUuid('id')->name('consultations.start');
        Route::post('consultations/{id}/end', [DoctorConsultationController::class, 'end'])->whereUuid('id')->name('consultations.end');
        Route::post('consultations/{id}/call', [DoctorConsultationController::class, 'call'])->whereUuid('id')->name('consultations.call');
        Route::get('consultations/{id}/messages', [ConsultationChatController::class, 'doctorMessages'])->whereUuid('id')->name('consultations.messages');
        Route::post('consultations/{id}/messages', [ConsultationChatController::class, 'doctorSend'])->whereUuid('id')->name('consultations.messages.send');
        Route::post('consultations/{id}/prescription', [PrescriptionController::class, 'issue'])->whereUuid('id')->name('consultations.prescription');
        Route::get('earnings', [DoctorConsultationController::class, 'earnings'])->name('earnings');
    });

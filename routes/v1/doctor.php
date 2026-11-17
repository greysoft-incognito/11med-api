<?php

use App\Enums\Role;
use App\Http\Controllers\Doctors\DoctorProfileController;
use Illuminate\Support\Facades\Route;

// The doctor's own record (app spec §4).
Route::middleware(['auth:sanctum', 'role:' . Role::Doctor->value])
    ->prefix('doctor')
    ->name('doctor.')
    ->group(function () {
        Route::get('profile', [DoctorProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile', [DoctorProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/submit', [DoctorProfileController::class, 'submit'])->name('profile.submit');
        Route::post('documents', [DoctorProfileController::class, 'document'])->name('documents.store');
    });

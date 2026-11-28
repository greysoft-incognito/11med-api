<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Doctors\DoctorApplicationController;
use Illuminate\Support\Facades\Route;

// Doctor verification on the team's desk (portal handover §6 "Doctors").
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/doctors')
    ->name('portal.admin.doctors.')
    ->group(function () {
        Route::get('/', [DoctorApplicationController::class, 'index'])
            ->name('index')
            ->middleware(AdminPermission::middleware(AdminPermission::DoctorsView));
        Route::get('{id}', [DoctorApplicationController::class, 'show'])
            ->whereUuid('id')
            ->name('show')
            ->middleware(AdminPermission::middleware(AdminPermission::DoctorsView));
        Route::post('{id}/documents/review', [DoctorApplicationController::class, 'reviewDocument'])
            ->whereUuid('id')
            ->name('documents.review')
            ->middleware(AdminPermission::middleware(AdminPermission::DoctorsReview));
        Route::post('{id}/review', [DoctorApplicationController::class, 'review'])
            ->whereUuid('id')
            ->name('review')
            ->middleware(AdminPermission::middleware(AdminPermission::DoctorsReview));
    });

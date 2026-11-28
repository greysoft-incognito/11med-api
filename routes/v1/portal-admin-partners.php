<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Partners\AdminPartnerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/partners')
    ->name('portal.admin.partners.')
    ->group(function () {
        Route::get('/', [AdminPartnerController::class, 'index'])
            ->name('index')
            ->middleware(AdminPermission::middleware(AdminPermission::PartnersView));
        Route::post('{id}/review', [AdminPartnerController::class, 'review'])
            ->whereUuid('id')
            ->name('review')
            ->middleware(AdminPermission::middleware(AdminPermission::PartnersReview));
        Route::post('{id}/welcome', [AdminPartnerController::class, 'resendWelcome'])
            ->whereUuid('id')
            ->name('welcome')
            ->middleware(AdminPermission::middleware(AdminPermission::PartnersReview));
    });

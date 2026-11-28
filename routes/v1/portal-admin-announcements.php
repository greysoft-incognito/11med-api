<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Announcements\AdminAnnouncementController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Announcements to app users, doctors, pharmacies or laboratories, now or at a time.
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
    ->prefix('portal/admin/announcements')
    ->name('portal.admin.announcements.')
    ->group(function () {
        Route::middleware(AdminPermission::middleware(AdminPermission::AnnouncementsView, AdminPermission::AnnouncementsSend))->group(function () {
            Route::get('/', [AdminAnnouncementController::class, 'index'])->name('index');
            Route::post('preview', [AdminAnnouncementController::class, 'preview'])->name('preview');
        });

        Route::middleware(AdminPermission::middleware(AdminPermission::AnnouncementsSend))->group(function () {
            Route::post('/', [AdminAnnouncementController::class, 'store'])->name('store');
            Route::post('{id}/cancel', [AdminAnnouncementController::class, 'cancel'])->whereUuid('id')->name('cancel');
        });
    });

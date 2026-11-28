<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Operations\AdminConsultationController;
use App\Http\Controllers\Operations\AdminLabBookingController;
use App\Http\Controllers\Operations\AdminOrderController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// Oversight of orders, lab bookings and consultations: find one, and cancel it with a refund.
Route::middleware(['auth:sanctum', RequireAccountKind::staff()])
    ->prefix('portal/admin')
    ->name('portal.admin.')
    ->group(function () {
        $desks = [
            'orders' => [AdminOrderController::class, AdminPermission::OrdersView, AdminPermission::OrdersCancel],
            'lab-bookings' => [AdminLabBookingController::class, AdminPermission::BookingsView, AdminPermission::BookingsCancel],
            'consultations' => [AdminConsultationController::class, AdminPermission::ConsultationsView, AdminPermission::ConsultationsCancel],
        ];

        foreach ($desks as $path => [$controller, $view, $cancel]) {
            Route::get($path, [$controller, 'index'])
                ->middleware(AdminPermission::middleware($view))
                ->name("{$path}.index");
            Route::get("{$path}/{id}", [$controller, 'show'])
                ->middleware(AdminPermission::middleware($view))
                ->whereUuid('id')
                ->name("{$path}.show");
            Route::post("{$path}/{id}/cancel", [$controller, 'cancel'])
                ->middleware(AdminPermission::middleware($cancel))
                ->whereUuid('id')
                ->name("{$path}.cancel");
        }
    });

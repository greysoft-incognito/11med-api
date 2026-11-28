<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Pharmacy\PortalDrugController;
use App\Http\Controllers\Pharmacy\PortalOrderController;
use App\Http\Controllers\Pharmacy\PortalPharmacyController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The pharmacy portal (portal handover §4): every route scoped to the account's own pharmacy.
Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Pharmacy])])
    ->prefix('portal/pharmacy')
    ->name('portal.pharmacy.')
    ->group(function () {
        Route::get('/', [PortalPharmacyController::class, 'show'])->name('show');
        Route::patch('/', [PortalPharmacyController::class, 'update'])->name('update');
        Route::get('summary', [PortalPharmacyController::class, 'summary'])->name('summary');

        Route::get('drugs', [PortalDrugController::class, 'index'])->name('drugs.index');
        Route::post('drugs', [PortalDrugController::class, 'store'])->name('drugs.store');
        Route::get('drugs/{id}', [PortalDrugController::class, 'show'])->whereUuid('id')->name('drugs.show');
        Route::patch('drugs/{id}', [PortalDrugController::class, 'update'])->whereUuid('id')->name('drugs.update');
        Route::delete('drugs/{id}', [PortalDrugController::class, 'destroy'])->whereUuid('id')->name('drugs.destroy');

        Route::get('orders', [PortalOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{id}', [PortalOrderController::class, 'show'])->whereUuid('id')->name('orders.show');
        Route::post('orders/{id}/advance', [PortalOrderController::class, 'advance'])->whereUuid('id')->name('orders.advance');
        Route::post('orders/{id}/cancel', [PortalOrderController::class, 'cancel'])->whereUuid('id')->name('orders.cancel');
    });

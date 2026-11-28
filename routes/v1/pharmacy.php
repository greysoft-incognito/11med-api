<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Pharmacy\CartController;
use App\Http\Controllers\Pharmacy\OrderController;
use App\Http\Controllers\Pharmacy\StoreController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

// The app's store (app spec §8): listed pharmacies, their medicines, the cart and orders.
Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Patient])])
    ->prefix('pharmacy')
    ->name('pharmacy.')
    ->group(function () {
        Route::get('pharmacies', [StoreController::class, 'pharmacies'])->name('pharmacies.index');
        Route::get('pharmacies/{pharmacyId}', [StoreController::class, 'pharmacy'])->whereUuid('pharmacyId')->name('pharmacies.show');
        Route::get('drugs', [StoreController::class, 'drugs'])->name('drugs.index');
        Route::get('drugs/{drugId}', [StoreController::class, 'drug'])->whereUuid('drugId')->name('drugs.show');

        Route::get('cart', [CartController::class, 'show'])->name('cart.show');
        Route::patch('cart', [CartController::class, 'update'])->name('cart.update');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:checkout')->name('orders.store');
        Route::get('orders/{orderId}', [OrderController::class, 'show'])->whereUuid('orderId')->name('orders.show');
        Route::post('orders/{orderId}/cancel', [OrderController::class, 'cancel'])->whereUuid('orderId')->name('orders.cancel');
    });

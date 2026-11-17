<?php

use App\Enums\Role;
use App\Http\Controllers\Laboratories\CatalogueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/catalogue')
    ->name('portal.admin.catalogue.')
    ->group(function () {
        Route::get('/', [CatalogueController::class, 'index'])->name('index');
        Route::post('/', [CatalogueController::class, 'store'])->name('store');
        Route::put('{code}', [CatalogueController::class, 'update'])->name('update');
    });

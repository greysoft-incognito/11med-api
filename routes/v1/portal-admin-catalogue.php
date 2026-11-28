<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Laboratories\CatalogueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/catalogue')
    ->name('portal.admin.catalogue.')
    ->group(function () {
        Route::get('/', [CatalogueController::class, 'index'])
            ->name('index')
            ->middleware(AdminPermission::middleware(AdminPermission::CatalogueView));
        Route::post('/', [CatalogueController::class, 'store'])
            ->name('store')
            ->middleware(AdminPermission::middleware(AdminPermission::CatalogueEdit));
        Route::put('{code}', [CatalogueController::class, 'update'])
            ->name('update')
            ->middleware(AdminPermission::middleware(AdminPermission::CatalogueEdit));
    });

<?php

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Http\Controllers\Documents\AdminDocumentController;
use Illuminate\Support\Facades\Route;

// The terms and privacy documents, edited by the team.
Route::middleware(['auth:sanctum', 'role:' . Role::Admin->value])
    ->prefix('portal/admin/documents')
    ->name('portal.admin.documents.')
    ->group(function () {
        Route::middleware(AdminPermission::middleware(AdminPermission::DocumentsView, AdminPermission::DocumentsEdit))->group(function () {
            Route::get('/', [AdminDocumentController::class, 'index'])->name('index');
            Route::get('{id}', [AdminDocumentController::class, 'show'])->whereUuid('id')->name('show');
        });

        Route::put('{id}', [AdminDocumentController::class, 'update'])
            ->whereUuid('id')
            ->middleware(AdminPermission::middleware(AdminPermission::DocumentsEdit))
            ->name('update');
    });

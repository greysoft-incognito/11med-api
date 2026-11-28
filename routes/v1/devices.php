<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Devices\DeviceController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireAccountKind::for(AccountKind::app())])
    ->prefix('devices')
    ->name('devices.')
    ->group(function () {
        Route::put('{installId}', [DeviceController::class, 'update'])->whereUuid('installId')->name('update');
        Route::delete('{installId}', [DeviceController::class, 'destroy'])->whereUuid('installId')->name('destroy');
    });

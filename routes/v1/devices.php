<?php

use App\Enums\Role;
use App\Http\Controllers\Devices\DeviceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . implode('|', Role::values(Role::app()))])
    ->prefix('devices')
    ->name('devices.')
    ->group(function () {
        Route::put('{installId}', [DeviceController::class, 'update'])->whereUuid('installId')->name('update');
        Route::delete('{installId}', [DeviceController::class, 'destroy'])->whereUuid('installId')->name('destroy');
    });

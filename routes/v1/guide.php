<?php

use App\Enums\Role;
use App\Http\Controllers\Guide\GuideController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:' . Role::Patient->value])
    ->prefix('guide/conversation')
    ->name('guide.')
    ->group(function () {
        Route::get('/', [GuideController::class, 'show'])->name('show');
        Route::post('messages', [GuideController::class, 'send'])->middleware('throttle:guide')->name('send');
        Route::delete('/', [GuideController::class, 'destroy'])->name('destroy');
    });

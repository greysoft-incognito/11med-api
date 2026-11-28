<?php

use App\Enums\AccountKind;
use App\Http\Controllers\Guide\GuideController;
use App\Http\Middleware\RequireAccountKind;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireAccountKind::for([AccountKind::Patient])])
    ->prefix('guide/conversation')
    ->name('guide.')
    ->group(function () {
        Route::get('/', [GuideController::class, 'show'])->name('show');
        Route::post('messages', [GuideController::class, 'send'])->middleware('throttle:guide')->name('send');
        Route::delete('/', [GuideController::class, 'destroy'])->name('destroy');
    });

<?php

use App\Http\Controllers\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('media')
    ->name('media.')
    ->group(function () {
        Route::post('/', [MediaController::class, 'store'])->middleware('throttle:media')->name('store');
        Route::get('{media}/file', [MediaController::class, 'file'])->middleware('signed')->name('file');
    });

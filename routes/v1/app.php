<?php

use App\Http\Controllers\AppVersionController;
use Illuminate\Support\Facades\Route;

Route::get('app/version', [AppVersionController::class, 'show'])->name('app.version');

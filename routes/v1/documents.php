<?php

use App\Http\Controllers\Documents\DocumentController;
use Illuminate\Support\Facades\Route;

// The terms and privacy documents the app and the portals show before asking for acceptance.
Route::get('documents/{slug}', [DocumentController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('documents.show');

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return [
        'api' => config('app.name'),
    ];
});

$files = glob(__DIR__ . '/api/*.php');
foreach ($files as $file) {
    Route::middleware('api')->group($file);
}

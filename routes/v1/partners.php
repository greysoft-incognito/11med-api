<?php

use App\Http\Controllers\Partners\PartnerApplicationController;
use Illuminate\Support\Facades\Route;

// The public application (concept §5.1): there is no account until the team approves.
Route::post('partners/apply', [PartnerApplicationController::class, 'store'])
    ->middleware('throttle:partner-apply')
    ->name('partners.apply');

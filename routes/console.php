<?php

use App\Models\OtpCode;
use App\Models\PasswordLink;
use App\Models\RefreshToken;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('model:prune', ['--model' => [RefreshToken::class, OtpCode::class, PasswordLink::class]])->daily();

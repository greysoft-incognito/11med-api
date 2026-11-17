<?php

use App\Models\Media;
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

// Uploads no record claimed within a day, with their files (app handover §12).
Schedule::command('model:prune', ['--model' => [Media::class]])->hourly();

// Payments: abandon gateway payments pending past the timeout, retry refunds never sent (app handover §16).
Schedule::command('payments:sweep')->everyFiveMinutes()->withoutOverlapping();

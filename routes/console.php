<?php

use App\Models\GuideConversation;
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

// Doses sixty minutes past due without an answer become missed, once each (M6).
Schedule::command('medications:settle-doses')->everyMinute()->withoutOverlapping();

// Compliance reports are signed for minutes; their files are removed after a day (M6).
Schedule::command('medications:prune-reports')->hourly();

// Guide conversations idle past the retention period, with their messages (app handover §11).
Schedule::command('model:prune', ['--model' => [GuideConversation::class]])->daily();

// Payments: abandon gateway payments pending past the timeout, retry refunds never sent (app handover §16).
Schedule::command('payments:sweep')->everyFiveMinutes()->withoutOverlapping();

// Consultations (M10): the accept and join deadlines and chat windows on a timer, never on read
// (app handover §2.3); payouts every 48 hours (§7.7).
Schedule::command('consultations:settle')->everyMinute()->withoutOverlapping();
Schedule::command('consultations:payouts')->cron('0 2 */2 * *')->withoutOverlapping();

// Announcements the team scheduled go out at their time (M17).
Schedule::command('announcements:dispatch')->everyMinute()->withoutOverlapping();

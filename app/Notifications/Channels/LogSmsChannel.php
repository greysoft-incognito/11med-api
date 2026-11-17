<?php

namespace App\Notifications\Channels;

use App\Notifications\Identity\SmsNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Stands in for the gateway until SMS is enabled. The text is logged only with debug on.
 */
class LogSmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof SmsNotification) {
            return;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification);
        $text = config('app.debug') ? $notification->smsText() : '[withheld]';

        Log::info("SMS to {$to}: {$text}");
    }
}

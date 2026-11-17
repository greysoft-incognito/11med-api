<?php

namespace App\Support;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Toneflix\KudiSmsNotification\KudiSmsChannel;

/**
 * A notifiable for a phone number with no account behind it yet, routed for every SMS channel.
 */
final class Sms
{
    public static function to(string $phone): AnonymousNotifiable
    {
        $number = PhoneNumber::international($phone);

        return Notification::route('sms', $number)
            ->route('twilio', $number)
            ->route(KudiSmsChannel::class, $number);
    }
}

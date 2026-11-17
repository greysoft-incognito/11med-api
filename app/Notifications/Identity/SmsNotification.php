<?php

namespace App\Notifications\Identity;

use App\Enums\SmsProvider;
use App\Notifications\Channels\LogSmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioSmsMessage;
use Toneflix\KudiSmsNotification\KudiSmsMessage;

/**
 * A text message through the kit's gateway when SMS is enabled, the log otherwise.
 * Sent to `Sms::to($phone)`.
 */
abstract class SmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function smsText(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [config('elevenmed.sms.enabled') ? SmsProvider::getChannel() : LogSmsChannel::class];
    }

    public function toKudiSms(object $notifiable): KudiSmsMessage
    {
        return (new KudiSmsMessage)->message($this->smsText());
    }

    public function toTwilio(object $notifiable): TwilioSmsMessage
    {
        return (new TwilioSmsMessage)->content($this->smsText());
    }
}

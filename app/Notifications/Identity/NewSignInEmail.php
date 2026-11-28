<?php

namespace App\Notifications\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that an account was signed in on a new device. It names the device and the time, never a code.
 */
class NewSignInEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $device,
        public readonly string $time,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.new_sign_in.title'))
            ->line(__('identity.new_sign_in.body', ['device' => $this->device, 'time' => $this->time]))
            ->line(__('identity.new_sign_in.advice'));
    }
}

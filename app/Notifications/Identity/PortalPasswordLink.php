<?php

namespace App\Notifications\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A portal reset link, or a new account's first-password link.
 */
class PortalPasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly bool $welcome,
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
        $kind = $this->welcome ? 'welcome' : 'reset';

        return (new MailMessage)
            ->subject(__("identity.portal_link.{$kind}.subject"))
            ->line(__("identity.portal_link.{$kind}.line"))
            ->action(__("identity.portal_link.{$kind}.action"), $this->url)
            ->line(__("identity.portal_link.{$kind}.expiry"));
    }
}

<?php

namespace App\Notifications\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An existing account given access to a portal: the admin desk or a partner's portal. It signs in
 * with the password it already has, so there is no link to set one.
 */
class PortalAccessGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $place,
        public readonly string $url,
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
            ->subject(__('identity.portal_access.subject'))
            ->line(__('identity.portal_access.line', ['place' => $this->place]))
            ->action(__('identity.portal_access.action'), $this->url)
            ->line(__('identity.portal_access.note'));
    }
}

<?php

namespace App\Notifications\Partners;

use App\Enums\PartnerStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a partner's accounts it was suspended, with the team's reason, or reinstated.
 */
class PartnerStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PartnerStatus $status,
        public readonly ?string $notes,
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
        $kind = $this->status === PartnerStatus::Suspended ? 'suspended' : 'reinstated';

        $mail = (new MailMessage)
            ->subject(__("partners.{$kind}.subject"))
            ->line(__("partners.{$kind}.line"));

        return $this->notes === null ? $mail : $mail->line($this->notes);
    }
}

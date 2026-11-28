<?php

namespace App\Notifications\Identity;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link to a finished data export. It works until the export is removed.
 */
class DataExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly CarbonInterface $expiresAt,
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
        $until = $this->expiresAt
            ->copy()
            ->setTimezone(config('elevenmed.timezone'))
            ->format('j F Y, g:i a');

        return (new MailMessage)
            ->subject(__('identity.data_export.subject'))
            ->line(__('identity.data_export.line'))
            ->action(__('identity.data_export.action'), $this->url)
            ->line(__('identity.data_export.expiry', ['until' => $until]));
    }
}

<?php

namespace App\Listeners\Identity;

use App\Enums\NotificationCategory;
use App\Events\Identity\NewSignIn;
use App\Notifications\Identity\NewSignInEmail;
use App\Services\Notifications\Notifier;

/**
 * Tells the owner their account was opened on a new device: in their notifications and, when
 * the account has a confirmed address, by email.
 */
class AlertNewSignIn
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function handle(NewSignIn $event): void
    {
        $user = $event->user;
        $device = $event->signIn->device_name ?? __('identity.new_sign_in.unknown_device');
        $at = $event->signIn->signed_in_at
            ->timezone(config('elevenmed.timezone'))
            ->format('j M Y, g:i a');

        $this->notifier->send(
            $user,
            NotificationCategory::Account,
            __('identity.new_sign_in.title'),
            __('identity.new_sign_in.body', ['device' => $device, 'time' => $at]),
            ['dedupe_key' => "new_sign_in:{$event->signIn->uuid}"],
        );

        if ($user->email !== null && $user->email_verified_at !== null) {
            $user->notify(new NewSignInEmail($device, $at));
        }
    }
}

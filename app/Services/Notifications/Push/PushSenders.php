<?php

namespace App\Services\Notifications\Push;

use App\Enums\PushProvider;

/**
 * The sender for a provider: the real service once push is enabled and that provider's
 * credentials are set, the log otherwise.
 */
class PushSenders
{
    public function for(PushProvider $provider): PushSender
    {
        if (! config('elevenmed.push.enabled')) {
            return app(LogPushSender::class);
        }

        return match ($provider) {
            PushProvider::Apns => filled(config('elevenmed.push.apns.key_path')) ? app(ApnsSender::class) : app(LogPushSender::class),
            PushProvider::Fcm => filled(config('elevenmed.push.fcm.credentials_path')) ? app(FcmSender::class) : app(LogPushSender::class),
        };
    }
}

<?php

namespace App\Services\Notifications\Push;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

/**
 * Stands in for APNs and FCM until push is enabled (HT-006). Logs ids only, never the text.
 */
class LogPushSender implements PushSender
{
    public function send(Device $device, PushMessage $message): PushOutcome
    {
        Log::info('push.logged', [
            'provider' => $device->push_provider->value,
            'install_id' => $device->install_id,
            'category' => $message->category->value,
            'notification_id' => $message->notificationId,
        ]);

        return PushOutcome::Delivered;
    }
}

<?php

namespace App\Services\Notifications\Push;

use App\Models\Device;

/**
 * Sends one push to one device through its provider.
 */
interface PushSender
{
    public function send(Device $device, PushMessage $message): PushOutcome;
}

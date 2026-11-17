<?php

namespace App\Jobs\Notifications;

use App\Models\Device;
use App\Services\Devices\Devices;
use App\Services\Notifications\Push\PushMessage;
use App\Services\Notifications\Push\PushOutcome;
use App\Services\Notifications\Push\PushSenders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one push to every install the user is signed in on, dropping tokens the provider refuses.
 */
class DeliverPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $userId,
        public readonly PushMessage $message,
    ) {}

    public function handle(Devices $devices, PushSenders $senders): void
    {
        $devices->eachOf($this->userId, function (Device $device) use ($devices, $senders) {
            $outcome = $senders
                ->for($device->push_provider)
                ->send($device, $this->message);

            if ($outcome === PushOutcome::InvalidToken) {
                $devices->dropInvalidToken($device);
            }
        });
    }
}

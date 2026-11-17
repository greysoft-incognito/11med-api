<?php

namespace App\Listeners\Devices;

use App\Events\Identity\SessionsEnded;
use App\Services\Devices\Devices;

/**
 * An install whose session ended on the server cannot unregister itself, so it is dropped here.
 */
class ForgetEndedDevices
{
    public function __construct(
        private readonly Devices $devices,
    ) {}

    public function handle(SessionsEnded $event): void
    {
        $this->devices->forget($event->user, $event->installIds);
    }
}

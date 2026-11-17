<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationCategory;
use App\Events\Medications\DoseMissed;
use App\Services\Notifications\Notifier;

/**
 * A missed dose goes into the patient's history once, keyed on the dose. It is not pushed: the
 * phone already reminded them, and a missed dose is routine, not an emergency.
 */
class NotifyMissedDose
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function handle(DoseMissed $event): void
    {
        $scheduledFor = $event->scheduledFor
            ->utc()
            ->format('Y-m-d\TH:i:s.v\Z');

        $this->notifier->send(
            $event->patient,
            NotificationCategory::MissedDose,
            __('notifications.missed_dose.title', ['name' => $event->medicationName]),
            __('notifications.missed_dose.body', ['strength' => $event->strength]),
            [
                'target_id' => $event->medicationId,
                'dedupe_key' => "missed_dose:{$event->medicationId}:{$scheduledFor}",
                'push' => false,
            ],
        );
    }
}

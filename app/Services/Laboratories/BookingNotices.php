<?php

namespace App\Services\Laboratories;

use App\Enums\NotificationCategory;
use App\Models\LabBooking;
use App\Services\Notifications\Notifier;

/**
 * What a laboratory's accounts are told when a booking reaches them or is called off: the
 * reference and what to do, never which tests.
 */
class BookingNotices
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function arrived(LabBooking $booking): void
    {
        $this->notifier->toOrganisation(
            $booking->laboratory->organisation,
            NotificationCategory::LabBooking,
            __('notifications.lab_booking.arrived_title', ['reference' => $booking->reference]),
            __('notifications.lab_booking.arrived_body'),
            ['target_id' => $booking->uuid, 'dedupe_key' => "lab_booking_arrived:{$booking->uuid}"],
        );
    }

    public function withdrawn(LabBooking $booking): void
    {
        $this->notifier->toOrganisation(
            $booking->laboratory->organisation,
            NotificationCategory::LabBooking,
            __('notifications.lab_booking.cancelled_title', ['reference' => $booking->reference]),
            __('notifications.lab_booking.cancelled_body'),
            ['target_id' => $booking->uuid, 'dedupe_key' => "lab_booking_cancelled:{$booking->uuid}"],
        );
    }
}

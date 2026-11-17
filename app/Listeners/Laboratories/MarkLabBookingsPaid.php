<?php

namespace App\Listeners\Laboratories;

use App\Events\Payments\PaymentSucceeded;
use App\Models\LabBooking;
use App\Services\Laboratories\BookingRecords;

/**
 * The gateway's word that a booking is paid, which puts it in front of the laboratory.
 */
class MarkLabBookingsPaid
{
    public function __construct(
        private readonly BookingRecords $bookings,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        if ($event->target instanceof LabBooking) {
            $this->bookings->markPaid([$event->target->uuid]);
        }
    }
}

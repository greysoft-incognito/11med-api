<?php

namespace App\Services\Laboratories;

use App\Enums\PaymentPurpose;
use App\Models\LabBooking;
use App\Services\Payments\Payments;
use Illuminate\Support\Collection;

/**
 * Attaches each booking's payment summary, read from the Payments module in one query.
 */
class BookingPayments
{
    public function __construct(
        private readonly Payments $payments,
    ) {}

    /**
     * @param  iterable<LabBooking>|LabBooking  $bookings
     */
    public function attach(iterable|LabBooking $bookings): void
    {
        $list = $bookings instanceof LabBooking ? collect([$bookings]) : Collection::wrap($bookings);

        $summaries = $this->payments->summariesFor(
            PaymentPurpose::LabBooking,
            $list
                ->pluck('uuid')
                ->all()
        );

        $list->each(fn(LabBooking $booking) => $booking->paymentSummary = $summaries[$booking->uuid] ?? null);
    }
}

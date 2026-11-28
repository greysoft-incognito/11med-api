<?php

namespace App\Services\Laboratories;

use App\Enums\LabBookingStatus;
use App\Enums\LabCancelledBy;
use App\Exceptions\ApiException;
use App\Models\LabBooking;
use App\Models\Media;
use App\Services\Payments\Payments;

/**
 * The one place a laboratory booking changes stage: paid, moved one step along
 * booked, sample collected, processing, results ready, or cancelled while still booked.
 * Callers hold the booking's row lock.
 */
class BookingLifecycle
{
    public function __construct(
        private readonly Payments $payments,
    ) {}

    /**
     * Returns whether this call marked it, false when it was paid already.
     */
    public function markPaid(LabBooking $booking): bool
    {
        if ($booking->paid_at !== null) {
            return false;
        }

        $booking->paid_at = now();
        $booking->save();

        return true;
    }

    /**
     * The next stage. Results ready needs the results document, since it means the patient can
     * open them (422 `RESULTS_REQUIRED`).
     */
    public function advance(LabBooking $booking, ?Media $results = null, ?string $resultsName = null): LabBookingStatus
    {
        if ($booking->status === LabBookingStatus::Cancelled) {
            throw ApiException::unprocessable('BOOKING_CANCELLED');
        }

        $next = $booking->status->next();

        if ($next === null) {
            throw ApiException::unprocessable('BOOKING_FINAL');
        }

        if ($booking->paid_at === null) {
            throw ApiException::unprocessable('PAYMENT_PENDING');
        }

        if ($next === LabBookingStatus::ResultsReady) {
            if ($results === null) {
                throw ApiException::unprocessable('RESULTS_REQUIRED', 'results_url');
            }

            $booking->fill([
                'results_media_id' => $results->id,
                'results_name' => $resultsName ?? $results->name,
                'results_at' => now(),
            ]);
            $booking->setRelation('results', $results);
        }

        $booking->status = $next;
        $booking->save();

        return $next;
    }

    /**
     * Cancel while still booked (422 `BOOKING_STARTED` once the sample is taken). A paid booking
     * is refunded in full; an unpaid one's payment is abandoned.
     */
    public function cancel(LabBooking $booking, LabCancelledBy $by, ?string $reason = null): void
    {
        if ($booking->status === LabBookingStatus::Cancelled) {
            throw ApiException::unprocessable('BOOKING_CANCELLED');
        }

        if ($booking->status !== LabBookingStatus::Booked) {
            throw ApiException::unprocessable('BOOKING_STARTED');
        }

        $booking->fill([
            'status' => LabBookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $by,
            'cancellation_reason' => $reason,
        ]);
        $booking->save();

        $this->payments->refund($booking);
    }
}

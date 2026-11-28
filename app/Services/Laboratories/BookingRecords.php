<?php

namespace App\Services\Laboratories;

use App\Models\LabBooking;
use App\Models\LabRequest;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Illuminate\Support\Facades\DB;

/**
 * What other modules' events do to bookings: the gateway's payment, and a patient's erasure.
 */
class BookingRecords
{
    public function __construct(
        private readonly BookingLifecycle $lifecycle,
        private readonly MediaLibrary $media,
        private readonly BookingNotices $notices,
    ) {}

    /**
     * Marks the bookings paid and tells each laboratory of the ones that were not already.
     *
     * @param  list<string>  $bookingIds
     */
    public function markPaid(array $bookingIds): void
    {
        $arrived = DB::transaction(fn() => LabBooking::query()
            ->with('laboratory.organisation')
            ->whereIn('uuid', $bookingIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn(LabBooking $booking) => $this->lifecycle->markPaid($booking)));

        $arrived->each(fn(LabBooking $booking) => $this->notices->arrived($booking));
    }

    /**
     * Removes the patient's bookings and requests for real, and the results files they carried.
     */
    public function forget(User $patient): void
    {
        $bookings = LabBooking::withTrashed()->where('patient_id', $patient->id);

        Media::query()
            ->whereIn('id', $bookings
                ->clone()
                ->whereNotNull('results_media_id')
                ->select('results_media_id'))
            ->lazyById(200)
            ->each(fn(Media $media) => $this->media->discard($media));

        $bookings->forceDelete();

        LabRequest::withTrashed()
            ->where('patient_id', $patient->id)
            ->forceDelete();
    }
}

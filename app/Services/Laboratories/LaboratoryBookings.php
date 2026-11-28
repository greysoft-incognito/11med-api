<?php

namespace App\Services\Laboratories;

use App\Enums\LabBookingStatus;
use App\Enums\LabCancelledBy;
use App\Enums\MediaPurpose;
use App\Enums\NotificationCategory;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\LabBooking;
use App\Models\Laboratory;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\Notifications\Notifier;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The laboratory portal's bookings (portal handover §5): only paid ones, moved one step at a time
 * to results, or cancelled while booked. The patient is told of each step; a push never names a
 * test or a value.
 */
class LaboratoryBookings
{
    /**
     * The `status` filter's extra value: every booking still with the laboratory.
     */
    public const OPEN = 'open';

    public function __construct(
        private readonly BookingLifecycle $lifecycle,
        private readonly BookingPayments $bookingPayments,
        private readonly MediaLibrary $media,
        private readonly Notifier $notifier,
    ) {}

    /**
     * By when the patient is expected; `q` over reference, patient name and phone.
     */
    public function of(Laboratory $laboratory, ?string $status, ?string $search, ListQuery $list): Page
    {
        $search = trim((string) $search);
        $pattern = LikePattern::contains($search);

        $query = $this
            ->visible($laboratory)
            ->select('lab_bookings.*')
            ->join('users', 'users.id', '=', 'lab_bookings.patient_id')
            ->with(['patient', 'request', 'results'])
            ->when($status === self::OPEN, fn(Builder $q) => $q->whereIn('lab_bookings.status', LabBookingStatus::openValues()))
            ->when($status !== null && $status !== self::OPEN, fn(Builder $q) => $q->where('lab_bookings.status', $status))
            ->when($search !== '', fn(Builder $q) => $q->where(fn(Builder $w) => $w
                ->whereLike('lab_bookings.reference', $pattern)
                ->orWhereLike('users.full_name', $pattern)
                ->orWhereLike('users.phone', $pattern)))
            ->orderBy('lab_bookings.scheduled_for')
            ->orderBy('lab_bookings.id');

        $page = $list->paginate($query);

        $this->bookingPayments->attach($page->items);

        return $page;
    }

    public function find(Laboratory $laboratory, string $bookingId): LabBooking
    {
        $booking = $this->locate($laboratory, $bookingId);

        return $this->present($booking);
    }

    /**
     * One step on. The last needs the results document, uploaded by this account as `lab_results`.
     *
     * @param  array{results_url?: string|null, results_name?: string|null}  $data
     */
    public function advance(User $account, Laboratory $laboratory, string $bookingId, array $data): LabBooking
    {
        [$booking, $status] = DB::transaction(function () use ($account, $laboratory, $bookingId, $data) {
            $booking = $this->locate($laboratory, $bookingId, lock: true);
            $results = null;

            if ($booking->status->next() === LabBookingStatus::ResultsReady && ! empty($data['results_url'])) {
                $results = $this->media->claim($account, $data['results_url'], MediaPurpose::LabResults, 'results_url');
            }

            $status = $this->lifecycle->advance($booking, $results, $data['results_name'] ?? null);

            return [$booking, $status];
        });

        $this->notifier->send(
            $booking->patient,
            NotificationCategory::LabResult,
            __("labs.push.{$status->value}_title"),
            __("labs.push.{$status->value}_body", ['reference' => $booking->reference]),
            ['target_id' => $booking->uuid]
        );

        return $this->present($booking);
    }

    public function cancel(Laboratory $laboratory, string $bookingId, string $reason): LabBooking
    {
        $booking = DB::transaction(function () use ($laboratory, $bookingId, $reason) {
            $booking = $this->locate($laboratory, $bookingId, lock: true);

            $this->lifecycle->cancel($booking, LabCancelledBy::Laboratory, $reason);

            return $booking;
        });

        $this->tellCancelled($booking);

        return $this->present($booking);
    }

    /**
     * Tells the patient a booking someone else cancelled is off, and that what they paid comes back.
     */
    public function tellCancelled(LabBooking $booking): void
    {
        $key = $booking->cancelled_by === LabCancelledBy::Team ? 'labs.push.team_cancelled' : 'labs.push.cancelled';

        $this->notifier->send(
            $booking->patient,
            NotificationCategory::LabResult,
            __("{$key}_title"),
            __("{$key}_body", ['reference' => $booking->reference]),
            ['target_id' => $booking->uuid]
        );
    }

    /**
     * Bookings the laboratory may see: its own, once paid.
     */
    private function visible(Laboratory $laboratory): Builder
    {
        return LabBooking::query()
            ->where('lab_bookings.laboratory_id', $laboratory->id)
            ->whereNotNull('lab_bookings.paid_at');
    }

    private function locate(Laboratory $laboratory, string $bookingId, bool $lock = false): LabBooking
    {
        $booking = $this
            ->visible($laboratory)
            ->where('uuid', $bookingId)
            ->when($lock, fn(Builder $q) => $q->lockForUpdate())
            ->first();

        if ($booking === null) {
            throw ApiException::notFound(LabBooking::NOT_FOUND);
        }

        return $booking;
    }

    private function present(LabBooking $booking): LabBooking
    {
        $booking->load(['patient', 'request', 'results']);
        $this->bookingPayments->attach($booking);

        return $booking;
    }
}

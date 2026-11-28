<?php

namespace App\Services\Laboratories;

use App\Enums\LabBookingStatus;
use App\Enums\LabCancelledBy;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\LabBooking;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Support\LikePattern;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every laboratory's bookings, for the team: found when someone calls, and cancelled through the
 * booking lifecycle's own rule. Results and the doctor's notes are not the team's to read.
 */
class BookingDesk
{
    public function __construct(
        private readonly BookingLifecycle $lifecycle,
        private readonly BookingPayments $bookingPayments,
        private readonly LaboratoryBookings $laboratoryBookings,
        private readonly AuditLog $audit,
    ) {}

    /**
     * Newest booked first; `q` over reference, patient name and phone, and laboratory name.
     */
    public function list(?string $status, ?string $search, ListQuery $list): Page
    {
        $search = trim((string) $search);
        $pattern = LikePattern::contains($search);
        $phone = preg_match(PhoneNumber::PATTERN, $search) === 1 ? LikePattern::contains(PhoneNumber::normalise($search)) : $pattern;

        $query = LabBooking::query()
            ->with(['patient', 'laboratory.organisation'])
            ->when($status === LaboratoryBookings::OPEN, fn(Builder $q) => $q->whereIn('status', LabBookingStatus::openValues()))
            ->when($status !== null && $status !== LaboratoryBookings::OPEN, fn(Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn(Builder $q) => $q->where(fn(Builder $q) => $q
                ->whereLike('reference', $pattern)
                ->orWhereHas('patient', fn(Builder $q) => $q
                    ->whereLike('full_name', $pattern)
                    ->orWhereLike('phone', $phone))
                ->orWhereHas('laboratory.organisation', fn(Builder $q) => $q->whereLike('name', $pattern))))
            ->orderByDesc('booked_at')
            ->orderByDesc('id');

        $page = $list->paginate($query);
        $this->bookingPayments->attach($page->items);

        return $page;
    }

    public function find(string $id): LabBooking
    {
        $booking = $this->locate($id);

        return $this->present($booking);
    }

    /**
     * Only while booked, as for the laboratory; a paid booking is refunded in full.
     */
    public function cancel(User $actor, string $id, string $reason): LabBooking
    {
        $booking = DB::transaction(function () use ($actor, $id, $reason) {
            $booking = $this->locate($id, lock: true);

            $this->lifecycle->cancel($booking, LabCancelledBy::Team, $reason);
            $this->audit->record($actor, 'lab_booking.cancelled', $booking, ['reason' => $reason]);

            return $booking;
        });

        $this->laboratoryBookings->tellCancelled($booking);

        return $this->present($booking);
    }

    private function locate(string $id, bool $lock = false): LabBooking
    {
        $booking = LabBooking::query()
            ->where('uuid', $id)
            ->when($lock, fn(Builder $q) => $q->lockForUpdate())
            ->first();

        return $booking ?? throw ApiException::notFound(LabBooking::NOT_FOUND);
    }

    private function present(LabBooking $booking): LabBooking
    {
        $booking->load(['patient', 'laboratory.organisation']);
        $this->bookingPayments->attach($booking);

        return $booking;
    }
}

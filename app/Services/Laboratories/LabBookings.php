<?php

namespace App\Services\Laboratories;

use App\Enums\LabBookingStatus;
use App\Enums\LabCancelledBy;
use App\Enums\LabCollection;
use App\Enums\PaymentChannel;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\LabBooking;
use App\Models\Laboratory;
use App\Models\LaboratoryTest;
use App\Models\User;
use App\Services\Payments\Payments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A patient's laboratory bookings (app handover §10): priced from the laboratory's own list,
 * born unpaid with the payment to open, cancellable while still booked.
 */
class LabBookings
{
    private const LATE_GRACE_SECONDS = 60;

    public function __construct(
        private readonly Directory $directory,
        private readonly LabRequests $requests,
        private readonly BookingLifecycle $lifecycle,
        private readonly BookingPayments $bookingPayments,
        private readonly Payments $payments,
        private readonly BookingNotices $notices,
    ) {}

    /**
     * @param  array<string, mixed>  $data  `zBookLabRequest`
     */
    public function book(User $patient, array $data): LabBooking
    {
        $laboratory = $this->directory->find($data['laboratory_id']);
        $collection = LabCollection::from($data['collection']);
        $codes = array_values(array_unique(array_map('strtoupper', $data['test_codes'])));
        $offers = $this->offers($laboratory, $codes);

        if ($collection === LabCollection::Home) {
            if ($laboratory->home_collection_fee_kobo === null) {
                throw ApiException::unprocessable('HOME_COLLECTION_UNAVAILABLE');
            }

            if (empty($data['address'])) {
                throw ApiException::badRequest('VALIDATION_FAILED', 'address', __('errors.ADDRESS_REQUIRED'));
            }
        }

        $scheduledFor = Carbon::parse($data['scheduled_for'])->utc();

        if ($scheduledFor->lt(now()->subSeconds(self::LATE_GRACE_SECONDS))) {
            throw ApiException::unprocessable('SCHEDULE_PAST', 'scheduled_for');
        }

        $booking = DB::transaction(function () use ($patient, $data, $laboratory, $collection, $codes, $offers, $scheduledFor) {
            $request = empty($data['request_id']) ? null : $this->requests->find($patient, $data['request_id'], lock: true);

            if ($request?->booking !== null) {
                throw ApiException::unprocessable('REQUEST_ALREADY_BOOKED', null, ['booking_id' => $request->booking->uuid]);
            }

            $tests = array_map(fn(string $code) => [
                'test_code' => $code,
                'test_name' => $offers[$code]->test->name,
                'price_kobo' => $offers[$code]->price_kobo,
            ], $codes);

            $testsKobo = array_sum(array_column($tests, 'price_kobo'));
            $fee = $collection === LabCollection::Home ? $laboratory->home_collection_fee_kobo : 0;
            $fasting = max(array_map(fn(string $code) => $offers[$code]->test->fasting_hours, $codes));

            $booking = LabBooking::create([
                'reference' => $this->freshReference(),
                'patient_id' => $patient->id,
                'laboratory_id' => $laboratory->id,
                'lab_request_id' => $request?->id,
                'tests' => $tests,
                'tests_kobo' => $testsKobo,
                'collection' => $collection,
                'collection_fee_kobo' => $fee,
                'total_kobo' => $testsKobo + $fee,
                'address' => $collection === LabCollection::Home ? $this->address($data['address']) : null,
                'scheduled_for' => $scheduledFor,
                'fasting_hours' => $fasting,
                'status' => LabBookingStatus::Booked,
                'booked_at' => now(),
            ]);

            $booking
                ->setRelation('laboratory', $laboratory)
                ->setRelation('patient', $patient)
                ->setRelation('request', $request);

            $channel = isset($data['channel']) ? PaymentChannel::from($data['channel']) : null;

            $this->payments->initialise([$booking], $patient, $channel);

            return $booking;
        });

        $this->bookingPayments->attach($booking);

        return $booking;
    }

    /**
     * The patient's bookings, newest first.
     */
    public function of(User $patient, ListQuery $list): Page
    {
        $query = LabBooking::query()
            ->with(['laboratory.organisation', 'request', 'results'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('booked_at')
            ->orderByDesc('id');

        $page = $list->paginate($query);

        $this->bookingPayments->attach($page->items);

        return $page;
    }

    public function find(User $patient, string $bookingId): LabBooking
    {
        $booking = $this->owned($patient, $bookingId)->first();

        if ($booking === null) {
            throw ApiException::notFound(LabBooking::NOT_FOUND);
        }

        $booking->load(['laboratory.organisation', 'request', 'results']);
        $this->bookingPayments->attach($booking);

        return $booking;
    }

    /**
     * Only while booked; the doctor's request behind it is open again once cancelled.
     */
    public function cancel(User $patient, string $bookingId): LabBooking
    {
        $booking = DB::transaction(function () use ($patient, $bookingId) {
            $booking = $this
                ->owned($patient, $bookingId)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                throw ApiException::notFound(LabBooking::NOT_FOUND);
            }

            $this->lifecycle->cancel($booking, LabCancelledBy::Patient);

            return $booking;
        });

        if ($booking->paid_at !== null) {
            $this->notices->withdrawn($booking);
        }

        return $this->find($patient, $bookingId);
    }

    /**
     * The laboratory's offers for `$codes`, keyed by code, each refused with the code it fails
     * on: not on the list (`TEST_NOT_OFFERED`) or not runnable now (`TEST_UNAVAILABLE`).
     *
     * @param  list<string>  $codes
     * @return array<string, LaboratoryTest>
     */
    private function offers(Laboratory $laboratory, array $codes): array
    {
        /** @var Collection<string, LaboratoryTest> $offers */
        $offers = LaboratoryTest::query()
            ->with('test')
            ->where('laboratory_id', $laboratory->id)
            ->whereHas('test', fn($q) => $q->whereIn('code', $codes))
            ->get()
            ->keyBy(fn(LaboratoryTest $offer) => $offer->test->code);

        foreach ($codes as $code) {
            $offer = $offers->get($code);

            if ($offer === null) {
                throw ApiException::unprocessable('TEST_NOT_OFFERED', null, ['test_code' => $code]);
            }

            if (! $offer->available) {
                throw ApiException::unprocessable('TEST_UNAVAILABLE', null, ['test_code' => $code]);
            }
        }

        return $offers->all();
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, string|null>
     */
    private function address(array $address): array
    {
        return [
            'line1' => $address['line1'],
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'],
            'state' => $address['state'],
            'directions' => $address['directions'] ?? null,
        ];
    }

    private function owned(User $patient, string $bookingId): Builder
    {
        return LabBooking::query()
            ->where('patient_id', $patient->id)
            ->where('uuid', $bookingId);
    }

    private function freshReference(): string
    {
        do {
            $reference = '11L-' . strtoupper(Str::random(8));
        } while (LabBooking::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }
}

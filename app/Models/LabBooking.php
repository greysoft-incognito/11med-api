<?php

namespace App\Models;

use App\Enums\LabBookingStatus;
use App\Enums\LabCancelledBy;
use App\Enums\LabCollection;
use App\Enums\PaymentPurpose;
use App\Exceptions\ApiException;
use App\Services\Payments\Payable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patient's booking at a laboratory (`zLabBooking`). `tests` holds
 * `{ test_code, test_name, price_kobo }` as priced when booked; `address` the delivery address
 * for home collection. Paid through the gateway before the laboratory sees it.
 */
class LabBooking extends Model implements Payable
{
    public const NOT_FOUND = 'LAB_BOOKING_NOT_FOUND';

    /**
     * The `zPaymentSummary` the clients see, attached by `BookingPayments` before rendering.
     *
     * @var array<string, mixed>|null
     */
    public ?array $paymentSummary = null;

    protected $fillable = [
        'reference',
        'patient_id',
        'laboratory_id',
        'lab_request_id',
        'tests',
        'tests_kobo',
        'collection',
        'collection_fee_kobo',
        'total_kobo',
        'address',
        'scheduled_for',
        'fasting_hours',
        'status',
        'booked_at',
        'paid_at',
        'results_media_id',
        'results_name',
        'results_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'tests' => 'array',
            'address' => 'array',
            'tests_kobo' => 'integer',
            'collection' => LabCollection::class,
            'collection_fee_kobo' => 'integer',
            'total_kobo' => 'integer',
            'scheduled_for' => 'datetime',
            'fasting_hours' => 'integer',
            'status' => LabBookingStatus::class,
            'booked_at' => 'datetime',
            'paid_at' => 'datetime',
            'results_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancelled_by' => LabCancelledBy::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    public function results(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'results_media_id');
    }

    public function paymentPurpose(): PaymentPurpose
    {
        return PaymentPurpose::LabBooking;
    }

    public function paymentTargetId(): string
    {
        return $this->uuid;
    }

    public function payer(): User
    {
        return $this->patient;
    }

    public function amountKobo(): int
    {
        return $this->total_kobo;
    }

    public function assertPayable(): void
    {
        if ($this->status === LabBookingStatus::Cancelled) {
            throw ApiException::unprocessable('BOOKING_CANCELLED');
        }
    }

    public function paymentReference(): string
    {
        return $this->reference;
    }

    public function paymentPartnerName(): ?string
    {
        return $this->laboratory?->organisation?->name;
    }
}

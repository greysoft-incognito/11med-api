<?php

namespace App\Models;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Services\Payments\Payable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One pharmacy's share of a checkout (`zOrder`, `zPharmacyOrder`). Its siblings share
 * `checkout_id` and one payment.
 */
class Order extends Model implements Payable
{
    public const NOT_FOUND = 'ORDER_NOT_FOUND';

    protected $fillable = [
        'reference',
        'checkout_id',
        'patient_id',
        'organisation_id',
        'status',
        'fulfilment',
        'subtotal_kobo',
        'delivery_fee_kobo',
        'total_kobo',
        'address',
        'payment_status',
        'payment_channel',
        'placed_at',
        'estimated_delivery_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'rider',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fulfilment' => Fulfilment::class,
            'payment_status' => PaymentStatus::class,
            'payment_channel' => PaymentChannel::class,
            'subtotal_kobo' => 'integer',
            'delivery_fee_kobo' => 'integer',
            'total_kobo' => 'integer',
            'address' => 'array',
            'rider' => 'array',
            'placed_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * What a pharmacy sees: paid orders and those refunded after, and pickups paid at the counter.
     */
    public function scopeVisibleToPharmacy(Builder $query): void
    {
        $query->where(fn(Builder $query) => $query
            ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Refunded])
            ->orWhere('payment_channel', PaymentChannel::Counter));
    }

    public function isAtCounter(): bool
    {
        return $this->payment_channel === PaymentChannel::Counter;
    }

    public function paymentPurpose(): PaymentPurpose
    {
        return PaymentPurpose::Order;
    }

    public function paymentTargetId(): string
    {
        return $this->uuid;
    }

    public function paymentReference(): string
    {
        return $this->reference;
    }

    public function paymentPartnerName(): ?string
    {
        return $this->organisation?->name;
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
        if ($this->status === OrderStatus::Cancelled) {
            throw ApiException::unprocessable('ORDER_CANCELLED');
        }
    }
}

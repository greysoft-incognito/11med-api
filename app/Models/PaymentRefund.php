<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A refund of one share, asked of the gateway and settled by its webhook.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $payment_target_id
 * @property int $amount_kobo
 * @property RefundStatus $status
 * @property ?string $gateway_refund_id
 * @property ?Carbon $requested_at
 * @property ?Carbon $processed_at
 * @property ?string $failure_reason
 * @property Payment $payment
 * @property PaymentTarget $share
 */
class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_id',
        'payment_target_id',
        'amount_kobo',
        'status',
        'gateway_refund_id',
        'requested_at',
        'processed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount_kobo' => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function share(): BelongsTo
    {
        return $this->belongsTo(PaymentTarget::class, 'payment_target_id');
    }
}

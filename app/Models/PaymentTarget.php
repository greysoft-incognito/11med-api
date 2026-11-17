<?php

namespace App\Models;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentShareState;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One target's share of a payment, with the reference and partner copied for the admin desk.
 *
 * @property int $id
 * @property int $payment_id
 * @property PaymentPurpose $purpose
 * @property string $target_id
 * @property string $target_reference
 * @property ?string $partner_name
 * @property int $amount_kobo
 * @property int $refunded_kobo
 * @property PaymentShareState $state
 * @property Payment $payment
 */
class PaymentTarget extends Model
{
    protected $fillable = [
        'payment_id',
        'purpose',
        'target_id',
        'target_reference',
        'partner_name',
        'amount_kobo',
        'refunded_kobo',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'state' => PaymentShareState::class,
            'amount_kobo' => 'integer',
            'refunded_kobo' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * What may still be refunded of this share.
     */
    public function refundableKobo(): int
    {
        return $this->amount_kobo - $this->refunded_kobo;
    }
}

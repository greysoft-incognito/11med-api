<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One gateway transaction (app `zPayment`), covering one or more targets of one purpose.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property ?int $user_id
 * @property PaymentPurpose $purpose
 * @property ?PaymentChannel $channel
 * @property PaymentStatus $status
 * @property int $amount_kobo
 * @property ?string $authorization_url
 * @property ?string $access_code
 * @property ?Carbon $paid_at
 * @property ?string $failure_reason
 * @property ?Carbon $checked_at
 * @property Carbon $created_at
 * @property Collection<int, PaymentTarget> $targets
 */
class Payment extends Model
{
    public const NOT_FOUND = 'PAYMENT_NOT_FOUND';

    protected $fillable = [
        'reference',
        'user_id',
        'purpose',
        'channel',
        'status',
        'amount_kobo',
        'authorization_url',
        'access_code',
        'paid_at',
        'failure_reason',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'channel' => PaymentChannel::class,
            'status' => PaymentStatus::class,
            'amount_kobo' => 'integer',
            'paid_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PaymentTarget::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * The public ids of what this payment covers.
     *
     * @return list<string>
     */
    public function targetIds(): array
    {
        return $this->targets
            ->pluck('target_id')
            ->values()
            ->all();
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function atCounter(): bool
    {
        return $this->channel === PaymentChannel::Counter;
    }
}

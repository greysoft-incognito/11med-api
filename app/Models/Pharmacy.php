<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pharmacy organisation's store settings: its logo, shopfront and delivery terms.
 */
class Pharmacy extends Model
{
    public const NOT_FOUND = 'PHARMACY_NOT_FOUND';

    /** The fee a newly approved pharmacy starts with, until it sets its own. */
    public const DEFAULT_DELIVERY_FEE_KOBO = 150_000;

    protected $fillable = [
        'organisation_id',
        'logo_media_id',
        'cover_media_id',
        'delivery_fee_kobo',
        'free_delivery_over_kobo',
        'delivery_minutes',
    ];

    protected function casts(): array
    {
        return [
            'delivery_fee_kobo' => 'integer',
            'free_delivery_over_kobo' => 'integer',
            'delivery_minutes' => 'integer',
            'rating' => 'float',
            'rating_count' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function drugs(): HasMany
    {
        return $this->hasMany(Drug::class, 'organisation_id', 'organisation_id');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * Its fee for what is bought from it, waived once that clears its own threshold.
     */
    public function deliveryFeeFor(int $subtotalKobo): int
    {
        $free = $this->free_delivery_over_kobo !== null && $subtotalKobo >= $this->free_delivery_over_kobo;

        return $free ? 0 : $this->delivery_fee_kobo;
    }
}

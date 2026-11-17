<?php

namespace App\Models;

use App\Enums\PartnerKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A laboratory's listing (`zLaboratory`), beside the organisation that holds its name, address
 * and status. The organisation's uuid is the id the clients see.
 */
class Laboratory extends Model
{
    public const NOT_FOUND = 'LAB_NOT_FOUND';

    public const DEFAULT_OPENS = '08:00';

    public const DEFAULT_CLOSES = '17:00';

    protected $fillable = [
        'organisation_id',
        'logo_media_id',
        'cover_media_id',
        'opens',
        'closes',
        'home_collection_fee_kobo',
        'rating',
        'rating_count',
    ];

    protected function casts(): array
    {
        return [
            'home_collection_fee_kobo' => 'integer',
            'rating' => 'float',
            'rating_count' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(LaboratoryTest::class);
    }

    /**
     * Laboratories in the directory: their organisation approved and not suspended.
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->whereHas('organisation', fn(Builder $q) => $q->listed(PartnerKind::Laboratory));
    }

    /**
     * Adds `tests_count`, the tests it can run now.
     */
    public function scopeWithTestsCount(Builder $query): Builder
    {
        return $query->withCount(['offers as tests_count' => fn(Builder $q) => $q->where('available', true)]);
    }
}

<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A patient's premium as the team recorded it. Premium holds through `premium_until`, a calendar
 * day in Lagos, and lapses on its own with nothing written.
 *
 * @property Carbon $premium_until
 */
class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'premium_until',
        'reference',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'premium_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->premium_until->toDateString() >= self::today();
    }

    /**
     * The instant premium ends: the start of the day after `premium_until`, in Lagos.
     */
    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->premium_until->toDateString(), config('elevenmed.timezone'))
            ->addDay()
            ->startOfDay();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('premium_until', '>=', self::today());
    }

    /**
     * Today's date in Lagos, which is the day premium is judged against.
     */
    public static function today(): string
    {
        return now(config('elevenmed.timezone'))->toDateString();
    }
}

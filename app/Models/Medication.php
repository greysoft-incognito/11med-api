<?php

namespace App\Models;

use App\Enums\DosageForm;
use App\Enums\DoseUnit;
use App\Enums\FrequencyType;
use App\Enums\WithFood;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A medication schedule (app `zMedication`, spec §6.2). Its doses are derived from `specific_times`
 * on each Lagos day from `start_date` to `end_date`; answered and missed ones are `DoseLog` rows.
 */
class Medication extends Model
{
    public const NOT_FOUND = 'MEDICATION_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'drug_name',
        'brand_name',
        'dosage_strength',
        'dosage_form',
        'dose_quantity',
        'dose_unit',
        'frequency_type',
        'specific_times',
        'start_date',
        'end_date',
        'with_food',
        'special_instructions',
        'condition_being_treated',
        'reminder_active',
        'caregiver_alerts',
        'refill_reminder_days',
        'estimated_supply_units',
        'paused_at',
        'settled_through',
        'prescribed_by_doctor_id',
        'prescription_id',
    ];

    protected function casts(): array
    {
        return [
            'dosage_form' => DosageForm::class,
            'dose_quantity' => 'float',
            'dose_unit' => DoseUnit::class,
            'frequency_type' => FrequencyType::class,
            'specific_times' => 'array',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'with_food' => WithFood::class,
            'reminder_active' => 'boolean',
            'caregiver_alerts' => 'boolean',
            'paused_at' => 'immutable_datetime',
            'settled_through' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by_doctor_id');
    }

    public function doseLogs(): HasMany
    {
        return $this->hasMany(DoseLog::class);
    }

    /**
     * Running and inside its course on `$today` (a Lagos date): what the free-tier cap counts.
     */
    public function isActive(CarbonImmutable $today): bool
    {
        return $this->paused_at === null
            && ($this->end_date === null || $this->end_date->toDateString() >= $today->toDateString());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query, CarbonImmutable $today): void
    {
        $query
            ->whereNull('paused_at')
            ->where(fn(Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today->toDateString()));
    }
}

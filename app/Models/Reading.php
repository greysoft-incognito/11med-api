<?php

namespace App\Models;

use App\Enums\GlucoseContext;
use App\Enums\ReadingType;

/**
 * A reading taken at home (app `zReading`, handover §14).
 */
class Reading extends Model
{
    public const NOT_FOUND = 'READING_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'type',
        'systolic',
        'diastolic',
        'pulse',
        'mmol_l',
        'context',
        'kg',
        'taken_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReadingType::class,
            'systolic' => 'integer',
            'diastolic' => 'integer',
            'pulse' => 'integer',
            'mmol_l' => 'float',
            'context' => GlucoseContext::class,
            'kg' => 'float',
            'taken_at' => 'immutable_datetime',
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\DoseState;
use App\Enums\SkipReason;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The answer to one dose (app `zDose`): the patient's, or `missed` from the sixty-minute rule.
 */
class DoseLog extends Model
{
    public const NOT_FOUND = 'DOSE_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'medication_id',
        'scheduled_for',
        'state',
        'skip_reason',
        'logged_at',
        'snooze_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'immutable_datetime',
            'state' => DoseState::class,
            'skip_reason' => SkipReason::class,
            'logged_at' => 'immutable_datetime',
            'snooze_count' => 'integer',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}

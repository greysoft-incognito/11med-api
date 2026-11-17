<?php

namespace App\Models;

use App\Enums\CaregiverStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A caregiver of a patient (app spec §6.5, `zCaregiver`): an invitation to a phone number until
 * accepted, then the account that may read the patient's schedule, and nothing else.
 */
class Caregiver extends Model
{
    public const NOT_FOUND = 'CAREGIVER_NOT_FOUND';

    protected $fillable = [
        'patient_id',
        'phone',
        'invite_code',
        'status',
        'caregiver_user_id',
        'invited_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaregiverStatus::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }
}

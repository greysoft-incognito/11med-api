<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tests a doctor asked for from a consultation (`zLabRequest`). `tests` holds
 * `{ test_code, test_name, reason }` per test, as issued.
 */
class LabRequest extends Model
{
    public const NOT_FOUND = 'LAB_REQUEST_NOT_FOUND';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'consultation_id',
        'doctor_name',
        'mdcn_number',
        'tests',
        'notes',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'tests' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * The booking that fulfils it, while one is not cancelled.
     */
    public function booking(): HasOne
    {
        return $this
            ->hasOne(LabBooking::class)
            ->where('status', '<>', 'cancelled');
    }
}

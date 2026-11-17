<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A prescription (app `zPrescription`): `drugs` holds the `zPrescribedDrug` items as issued.
 * Medical data: never logged.
 */
class Prescription extends Model
{
    public const NOT_FOUND = 'NO_PRESCRIPTION';

    protected $fillable = [
        'consultation_id',
        'doctor_id',
        'doctor_name',
        'mdcn_number',
        'diagnosis_summary',
        'drugs',
        'issued_at',
        'expires_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'drugs' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}

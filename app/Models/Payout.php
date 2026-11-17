<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A doctor's payout (app `zPayout`, portal `zAdminPayout`): gross, commission and net, all three.
 */
class Payout extends Model
{
    public const NOT_FOUND = 'PAYOUT_NOT_FOUND';

    protected $fillable = [
        'doctor_id',
        'doctor_name',
        'bank_name',
        'bank_account_masked',
        'gross_kobo',
        'commission_percent',
        'commission_kobo',
        'net_kobo',
        'consultation_count',
        'status',
        'period_start',
        'period_end',
        'paid_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'gross_kobo' => 'integer',
            'commission_percent' => 'float',
            'commission_kobo' => 'integer',
            'net_kobo' => 'integer',
            'consultation_count' => 'integer',
            'status' => PayoutStatus::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }
}

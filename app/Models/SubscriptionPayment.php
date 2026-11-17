<?php

namespace App\Models;

/**
 * One premium payment the team recorded, kept by its outside reference for the audit trail.
 */
class SubscriptionPayment extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'premium_until',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'premium_until' => 'date',
            'recorded_at' => 'datetime',
        ];
    }
}

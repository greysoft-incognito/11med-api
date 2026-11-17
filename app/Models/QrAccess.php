<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * One open of an emergency page: when, and nothing about who (portal handover §7).
 */
class QrAccess extends Eloquent
{
    public $timestamps = false;

    protected $fillable = [
        'qr_token_id',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
        ];
    }
}

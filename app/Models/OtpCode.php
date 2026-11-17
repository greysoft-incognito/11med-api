<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Prunable;

class OtpCode extends Eloquent
{
    use Prunable;

    protected $fillable = [
        'target',
        'code_hash',
        'attempts',
        'resend',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'resend' => 'boolean',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}

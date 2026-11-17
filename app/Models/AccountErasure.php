<?php

namespace App\Models;

class AccountErasure extends Model
{
    protected $fillable = [
        'role',
        'reason',
        'erased_at',
    ];

    protected function casts(): array
    {
        return [
            'erased_at' => 'datetime',
        ];
    }
}

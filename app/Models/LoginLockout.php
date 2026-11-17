<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class LoginLockout extends Eloquent
{
    protected $fillable = [
        'phone',
        'failures',
        'locked_until',
        'hard_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_until' => 'datetime',
            'hard_locked_at' => 'datetime',
        ];
    }
}

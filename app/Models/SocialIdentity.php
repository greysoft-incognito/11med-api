<?php

namespace App\Models;

use App\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialIdentity extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'subject',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

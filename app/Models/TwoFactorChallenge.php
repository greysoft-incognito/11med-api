<?php

namespace App\Models;

use App\Enums\AuthClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sign-in waiting on its second step. A credential: not soft deleted, pruned a day after it lapses.
 */
class TwoFactorChallenge extends Eloquent
{
    use Prunable;

    protected $fillable = [
        'user_id',
        'token_hash',
        'client',
        'install_id',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'client' => AuthClient::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDay());
    }
}

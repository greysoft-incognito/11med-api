<?php

namespace App\Models;

use App\Enums\AuthClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A credential, not a record: revoked rather than deleted while live, pruned a week after it ends.
 */
class RefreshToken extends Eloquent
{
    use Prunable;

    protected $fillable = [
        'user_id',
        'family',
        'token_hash',
        'access_token_id',
        'install_id',
        'client',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'client' => AuthClient::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()
            ->where('expires_at', '<', now()->subWeek())
            ->orWhere('revoked_at', '<', now()->subWeek());
    }
}

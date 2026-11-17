<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A credential: used once, pruned a week after it ends.
 */
class PasswordLink extends Eloquent
{
    use Prunable;

    public const RESET = 'reset';

    public const WELCOME = 'welcome';

    protected $fillable = [
        'user_id',
        'token_hash',
        'kind',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
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
        return static::where('expires_at', '<', now()->subWeek());
    }
}

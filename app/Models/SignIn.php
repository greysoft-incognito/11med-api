<?php

namespace App\Models;

use App\Enums\AuthClient;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed-in session: one per refresh-token family. Like its tokens, a credential rather than a
 * record: ended rather than deleted while live, pruned a month after it ends.
 */
class SignIn extends Eloquent
{
    use HasPublicId;
    use Prunable;

    public const NOT_FOUND = 'SESSION_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'family',
        'client',
        'install_id',
        'device_key',
        'device_name',
        'platform',
        'signed_in_at',
        'last_used_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'client' => AuthClient::class,
            'signed_in_at' => 'datetime',
            'last_used_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function prunable(): Builder
    {
        return static::query()->where('ended_at', '<', now()->subMonth());
    }
}

<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PushProvider;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An app install that receives push (app handover §8.1).
 */
class Device extends Model
{
    protected $fillable = [
        'user_id',
        'install_id',
        'platform',
        'push_provider',
        'push_token',
        'app_version',
        'locale',
        'registered_at',
    ];

    protected $hidden = [
        'push_token',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'push_provider' => PushProvider::class,
            'registered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

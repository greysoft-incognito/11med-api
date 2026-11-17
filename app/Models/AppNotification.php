<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a user's notification history (app `zNotification`).
 */
class AppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'title',
        'body',
        'target_id',
        'dedupe_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

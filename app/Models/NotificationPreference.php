<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved delivery choice for one category (app `zNotificationPreference`).
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'push',
        'sms',
        'in_app',
    ];

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'push' => 'boolean',
            'sms' => 'boolean',
            'in_app' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

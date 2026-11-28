<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the team told an audience (portal `zAnnouncement`).
 */
class Announcement extends Model
{
    public const NOT_FOUND = 'ANNOUNCEMENT_NOT_FOUND';

    protected $fillable = [
        'title',
        'body',
        'audience',
        'push',
        'status',
        'send_at',
        'sent_at',
        'cancelled_at',
        'recipients',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'status' => AnnouncementStatus::class,
            'push' => 'boolean',
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'recipients' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

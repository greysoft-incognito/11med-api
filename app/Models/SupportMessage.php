<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message of a support ticket (clients' `zSupportMessage`). The body may describe symptoms, so it is encrypted and never logged.
 */
class SupportMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'author_id',
        'from_team',
        'body',
        'attachment_media_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'from_team' => 'boolean',
            'body' => 'encrypted',
            'sent_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'attachment_media_id');
    }
}

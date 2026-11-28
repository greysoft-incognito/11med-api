<?php

namespace App\Models;

use App\Enums\SupportCategory;
use App\Enums\SupportLinkKind;
use App\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support conversation (clients' `zSupportTicket`).
 */
class SupportTicket extends Model
{
    public const NOT_FOUND = 'TICKET_NOT_FOUND';

    protected $fillable = [
        'requester_id',
        'category',
        'subject',
        'status',
        'link_kind',
        'link_id',
        'assignee_id',
        'last_activity_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupportCategory::class,
            'status' => SupportStatus::class,
            'link_kind' => SupportLinkKind::class,
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}

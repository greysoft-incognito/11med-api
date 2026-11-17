<?php

namespace App\Models;

use App\Enums\GuideSender;
use App\Enums\GuideUrgency;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a guide conversation (app `zGuideMessage`). The body is encrypted at rest.
 */
class GuideMessage extends Eloquent
{
    use HasPublicId;

    protected $fillable = [
        'conversation_id',
        'sender',
        'body',
        'actions',
        'urgency',
        'suggestions',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sender' => GuideSender::class,
            'body' => 'encrypted',
            'actions' => 'array',
            'urgency' => GuideUrgency::class,
            'suggestions' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(GuideConversation::class, 'conversation_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A patient's conversation with the guide (app `zGuideConversation`). Symptoms are described in
 * it, so it is not a record: starting over deletes it, and one left idle past the retention period
 * is pruned with its messages.
 */
class GuideConversation extends Eloquent
{
    use HasPublicId;
    use MassPrunable;

    protected $fillable = [
        'user_id',
        'started_at',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GuideMessage::class, 'conversation_id');
    }

    public function prunable(): Builder
    {
        return static::query()->where('last_message_at', '<', now()->subDays(config('elevenmed.guide.retention_days')));
    }
}

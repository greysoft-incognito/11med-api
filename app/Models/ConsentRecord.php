<?php

namespace App\Models;

use App\Enums\ConsentContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A consent as given (app handover §3.2). `subject` names what was consented to, when it is a record.
 */
class ConsentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'context',
        'policy_version',
        'accepted_at',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => ConsentContext::class,
            'accepted_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

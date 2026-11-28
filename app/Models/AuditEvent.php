<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One thing the team did. Written once by `DatabaseAuditLog` and never changed or removed.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $actor_id
 * @property string $actor_name
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed> $meta
 * @property Carbon $created_at
 */
class AuditEvent extends Eloquent
{
    use HasPublicId;

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'action',
        'subject_type',
        'subject_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn() => throw new LogicException('Audit events are never changed.'));
        static::deleting(fn() => throw new LogicException('Audit events are never removed.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes one `audit_events` row per call. The subject is named by its type and the id the clients see.
 */
class DatabaseAuditLog implements AuditLog
{
    public function record(User $actor, string $action, Model $subject, array $meta = []): void
    {
        AuditEvent::create([
            'actor_id' => $actor->id,
            'actor_name' => (string) ($actor->full_name ?: $actor->email),
            'action' => $action,
            'subject_type' => Str::snake(class_basename($subject)),
            'subject_id' => (string) $subject->getRouteKey(),
            'meta' => $meta,
        ]);
    }
}

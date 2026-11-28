<?php

namespace Tests\Fakes;

use App\Models\User;
use App\Services\Audit\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps every audit record in memory for assertions.
 */
class RecordingAuditLog implements AuditLog
{
    /** @var list<array{actor: int, action: string, subject: Model, meta: array<string, mixed>}> */
    public array $records = [];

    public function record(User $actor, string $action, Model $subject, array $meta = []): void
    {
        $this->records[] = ['actor' => $actor->id, 'action' => $action, 'subject' => $subject, 'meta' => $meta];
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return array_column($this->records, 'action');
    }
}

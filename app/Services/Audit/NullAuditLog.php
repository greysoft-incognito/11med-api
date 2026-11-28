<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in until the audit table lands: records nothing.
 */
class NullAuditLog implements AuditLog
{
    public function record(User $actor, string $action, Model $subject, array $meta = []): void {}
}

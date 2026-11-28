<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Database\Eloquent\Model;

/**
 * The record of what the team did. Every admin write calls it once, inside its transaction.
 */
#[Bind(NullAuditLog::class)]
interface AuditLog
{
    /**
     * `$action` is `<area>.<past-tense verb>`, such as `doctor.reviewed`. `$meta` holds what the
     * action decided (a status, a reason, an amount), never medical data.
     *
     * @param  array<string, mixed>  $meta
     */
    public function record(User $actor, string $action, Model $subject, array $meta = []): void;
}

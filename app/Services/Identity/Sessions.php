<?php

namespace App\Services\Identity;

use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\User;
use Illuminate\Container\Attributes\Bind;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A user's signed-in sessions, one per sign-in (refresh-token family).
 */
#[Bind(TokenSessions::class)]
interface Sessions
{
    /**
     * The live sessions, most recently used first. With the caller's token, the session it
     * belongs to is marked `current`.
     */
    public function list(User $user, ListQuery $query, ?PersonalAccessToken $current = null): Page;

    /**
     * End one of the user's sessions, by its public id.
     */
    public function end(User $user, string $sessionId): void;

    /**
     * End every session of the user, except the family named.
     */
    public function revokeAll(User $user, ?string $exceptFamily = null): void;
}

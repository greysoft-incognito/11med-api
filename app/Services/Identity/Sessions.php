<?php

namespace App\Services\Identity;

use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * A user's signed-in sessions, one per sign-in (refresh-token family).
 */
#[Bind(TokenSessions::class)]
interface Sessions
{
    /**
     * The live sessions, newest first.
     */
    public function list(User $user, ListQuery $query): Page;

    /**
     * End every session of the user, except the family named.
     */
    public function revokeAll(User $user, ?string $exceptFamily = null): void;
}

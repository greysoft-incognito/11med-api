<?php

namespace App\Services\Identity;

use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\RefreshToken;
use App\Models\User;

/**
 * Stand-in until the sessions table lands: lists the live refresh tokens and revokes through SessionTokens.
 */
class TokenSessions implements Sessions
{
    public function __construct(
        private readonly SessionTokens $tokens,
    ) {}

    public function list(User $user, ListQuery $query): Page
    {
        $live = RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id');

        return $query->paginate($live);
    }

    public function revokeAll(User $user, ?string $exceptFamily = null): void
    {
        $this->tokens->revokeAll($user, $exceptFamily);
    }
}

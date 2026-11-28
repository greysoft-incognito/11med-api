<?php

namespace App\Services\Identity;

use App\Enums\AuthClient;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\SignIn;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sessions as `sign_ins` rows, ended through SessionTokens so their tokens die with them.
 */
class TokenSessions implements Sessions
{
    public function __construct(
        private readonly SessionTokens $tokens,
    ) {}

    public function list(User $user, ListQuery $query, ?PersonalAccessToken $current = null): Page
    {
        $family = $current === null ? null : $this->tokens->familyOf($current);

        $live = SignIn::query()
            ->where('user_id', $user->id)
            ->live()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id');

        $page = $query->paginate($live);

        $page->items->each(fn(SignIn $signIn) => $signIn->setAttribute('current', $signIn->family === $family));

        return $page;
    }

    public function end(User $user, string $sessionId): void
    {
        $signIn = SignIn::query()
            ->where('user_id', $user->id)
            ->where('uuid', $sessionId)
            ->live()
            ->first() ?? throw ApiException::notFound(SignIn::NOT_FOUND);

        $this->tokens->revokeFamily($signIn->family);
    }

    public function revokeAll(User $user, ?string $exceptFamily = null): void
    {
        $this->tokens->revokeAll($user, $exceptFamily);
    }

    public function revokeClient(User $user, AuthClient $client): void
    {
        $this->tokens->revokeClient($user, $client);
    }
}

<?php

namespace App\Services\Profile;

use App\Exceptions\ApiException;
use App\Models\QrToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Emergency QR tokens (app spec §3.3, handover §3.3). One current token per account, made on first
 * ask; revoking replaces it, and the old one stops opening the page at once.
 */
class QrCodes
{
    public function current(User $caller, string $userId): QrToken
    {
        if ($caller->uuid !== $userId) {
            throw ApiException::notFound();
        }

        return $this->currentFor($caller);
    }

    public function revoke(User $user): QrToken
    {
        return DB::transaction(function () use ($user) {
            QrToken::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->update(['revoked_at' => now()]);

            return $this->issue($user);
        });
    }

    /**
     * The page a hospital opens: the emergency address with the token.
     */
    public function url(QrToken $token): string
    {
        return config('elevenmed.emergency_url') . '?' . http_build_query(['token' => $token->token]);
    }

    private function currentFor(User $user): QrToken
    {
        return DB::transaction(function () use ($user) {
            $user->newQuery()->lockForUpdate()->find($user->id);

            $token = QrToken::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->first();

            return $token ?? $this->issue($user);
        });
    }

    private function issue(User $user): QrToken
    {
        $secret = Str::random(40);

        return QrToken::create([
            'user_id' => $user->id,
            'token' => $secret,
            'token_hash' => QrToken::hash($secret),
            'issued_at' => now(),
        ]);
    }
}

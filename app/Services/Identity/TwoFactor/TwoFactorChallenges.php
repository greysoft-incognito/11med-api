<?php

namespace App\Services\Identity\TwoFactor;

use App\Enums\AuthClient;
use App\Enums\HttpStatus;
use App\Exceptions\ApiException;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\Identity\SessionTokens;
use App\Services\Identity\TokenPair;
use Illuminate\Support\Str;

/**
 * The second step of a sign-in. A correct password for an account with two-step verification
 * answers 403 `TWO_FACTOR_REQUIRED` with a challenge token; the code completes it with the tokens.
 */
class TwoFactorChallenges
{
    public function __construct(
        private readonly TwoFactorCodes $codes,
        private readonly SessionTokens $tokens,
    ) {}

    /**
     * Refuse the sign-in with a challenge when the account has two-step verification on.
     */
    public function requireFor(User $user, AuthClient $client, ?string $installId = null): void
    {
        if (! $user->hasTwoFactor()) {
            return;
        }

        $token = Str::random(64);
        $minutes = config('elevenmed.two_factor.challenge_minutes');

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'client' => $client,
            'install_id' => $installId,
            'expires_at' => now()->addMinutes($minutes),
        ]);

        throw new ApiException(HttpStatus::FORBIDDEN, 'TWO_FACTOR_REQUIRED', meta: [
            'challenge_token' => $token,
            'expires_in' => $minutes * 60,
        ]);
    }

    /**
     * Spend the challenge with a code and sign the person in. Five wrong codes end the challenge.
     *
     * @return array{0: User, 1: TokenPair}
     */
    public function complete(string $token, AuthClient $client, string $code): array
    {
        $challenge = TwoFactorChallenge::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('client', $client)
            ->first();

        if ($challenge === null || $challenge->used_at !== null || $challenge->expires_at->isPast() || $challenge->user === null) {
            throw ApiException::unprocessable('TWO_FACTOR_CHALLENGE_INVALID', 'challenge_token');
        }

        if (! $this->codes->verify($challenge->user, $code)) {
            $challenge->attempts++;
            $challenge->used_at = $challenge->attempts >= config('elevenmed.two_factor.challenge_attempts') ? now() : null;
            $challenge->save();

            throw ApiException::badRequest('TWO_FACTOR_CODE_INVALID', 'code');
        }

        $spent = TwoFactorChallenge::query()
            ->whereKey($challenge->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        if ($spent === 0) {
            throw ApiException::unprocessable('TWO_FACTOR_CHALLENGE_INVALID', 'challenge_token');
        }

        $pair = $this->tokens->issue($challenge->user, $client, $challenge->install_id);

        return [$challenge->user, $pair];
    }
}

<?php

namespace App\Services\Identity\TwoFactor;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Turning two-step verification on and off: a new secret, its confirmation by a first code,
 * recovery codes, and the password plus a code to turn it off.
 */
class TwoFactorSetup
{
    public function __construct(
        private readonly Google2FA $totp,
        private readonly TwoFactorCodes $codes,
    ) {}

    /**
     * A fresh secret, waiting for its first code. Starting again replaces an unconfirmed one.
     */
    public function begin(User $user, string $password): PendingSecret
    {
        $this->checkPassword($user, $password);

        if ($user->hasTwoFactor()) {
            throw ApiException::conflict('TWO_FACTOR_ALREADY_ENABLED');
        }

        $secret = $this->totp->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        $label = $user->email ?? $user->phone ?? $user->full_name;
        $url = $this->totp->getQRCodeUrl(config('elevenmed.two_factor.issuer'), (string) $label, $secret);

        return new PendingSecret($secret, $url);
    }

    /**
     * The first code proves the authenticator holds the secret; the answer is the recovery codes.
     *
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactor()) {
            throw ApiException::conflict('TWO_FACTOR_ALREADY_ENABLED');
        }

        if ($user->two_factor_secret === null) {
            throw ApiException::unprocessable('TWO_FACTOR_NOT_STARTED');
        }

        if (! $this->codes->verifyTotp($user, $code)) {
            throw ApiException::badRequest('TWO_FACTOR_CODE_INVALID', 'code');
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->codes->regenerate($user);
    }

    public function disable(User $user, string $password, string $code): void
    {
        $this->checkPassword($user, $password);
        $this->requireEnabled($user);

        if (! $this->codes->verify($user, $code)) {
            throw ApiException::badRequest('TWO_FACTOR_CODE_INVALID', 'code');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /**
     * New recovery codes; the old ones stop working.
     *
     * @return list<string>
     */
    public function regenerateCodes(User $user, string $password): array
    {
        $this->checkPassword($user, $password);
        $this->requireEnabled($user);

        return $this->codes->regenerate($user);
    }

    private function requireEnabled(User $user): void
    {
        if (! $user->hasTwoFactor()) {
            throw ApiException::unprocessable('TWO_FACTOR_NOT_ENABLED');
        }
    }

    private function checkPassword(User $user, string $password): void
    {
        if ($user->password === null || ! Hash::check($password, $user->password)) {
            throw ApiException::unprocessable('PASSWORD_INCORRECT', 'password');
        }
    }
}

<?php

namespace App\Services\Identity\TwoFactor;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Checking a second-step code: six digits from the authenticator, never the same time step twice,
 * or one of the single-use recovery codes.
 */
class TwoFactorCodes
{
    public function __construct(
        private readonly Google2FA $totp,
    ) {}

    public function verify(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        return preg_match('/^\d{6}$/', $code) === 1
            ? $this->verifyTotp($user, $code)
            : $this->spendRecoveryCode($user, $code);
    }

    public function verifyTotp(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        return DB::transaction(function () use ($user, $code) {
            $locked = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->first(['id', 'two_factor_last_step']);

            $step = $this->totp->verifyKeyNewer($user->two_factor_secret, $code, $locked->two_factor_last_step ?? 0, 1);

            if (! is_int($step)) {
                return false;
            }

            $user->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        });
    }

    /**
     * New recovery codes, stored as hashes; the plain codes are shown once.
     *
     * @return list<string>
     */
    public function regenerate(User $user): array
    {
        $codes = collect(range(1, config('elevenmed.two_factor.recovery_codes')))
            ->map(fn() => Str::lower(Str::random(5) . '-' . Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn(string $code) => self::hash($code), $codes),
        ])->save();

        return $codes;
    }

    private function spendRecoveryCode(User $user, string $code): bool
    {
        $hash = self::hash(Str::lower($code));

        return DB::transaction(function () use ($user, $hash) {
            $locked = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->first();

            $left = $locked->two_factor_recovery_codes ?? [];

            if (! in_array($hash, $left, true)) {
                return false;
            }

            $remaining = array_values(array_filter($left, fn(string $stored) => ! hash_equals($stored, $hash)));

            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

            return true;
        });
    }

    private static function hash(string $code): string
    {
        return hash('sha256', $code);
    }
}

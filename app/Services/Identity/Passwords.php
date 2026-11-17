<?php

namespace App\Services\Identity;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Identity\PhoneCode;
use App\Support\PhoneNumber;
use App\Support\Sms;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * An app password: reset over a phone code, changed with the current one.
 * Either ends the account's other sessions.
 */
class Passwords
{
    public function __construct(
        private readonly AppAccounts $accounts,
        private readonly OneTimeCodes $codes,
        private readonly LoginGuard $guard,
        private readonly SessionTokens $tokens,
    ) {}

    /**
     * A reset code, within the resend budget. The answer never says whether the number has an account.
     */
    public function forgot(string $phone): CodeSent
    {
        $phone = PhoneNumber::normalise($phone);
        $sent = $this->codes->resend(AppAccounts::target($phone));

        if ($this->accounts->findByPhone($phone) !== null) {
            Sms::to($phone)->notify(new PhoneCode($sent->code, intdiv($sent->expiresIn, 60)));
        }

        return $sent;
    }

    public function reset(string $phone, string $code, string $password): void
    {
        $phone = PhoneNumber::normalise($phone);
        $user = $this->accounts->findByPhone($phone)
            ?? throw ApiException::badRequest('AUTH_INVALID_OTP', 'code');

        $this->codes->consume(AppAccounts::target($phone), $code);

        $user->forceFill([
            'password' => $password,
            'phone_verified_at' => $user->phone_verified_at ?? now(),
        ])->save();

        $this->guard->clear($phone);
        $this->tokens->revokeAll($user);
    }

    /**
     * The current password is the proof; the session making the change survives.
     */
    public function change(User $user, PersonalAccessToken $current, string $currentPassword, string $newPassword): void
    {
        if ($user->password === null || ! Hash::check($currentPassword, $user->password)) {
            throw ApiException::unprocessable('PASSWORD_INCORRECT', 'current_password');
        }

        if ($newPassword === $currentPassword) {
            throw ApiException::unprocessable('PASSWORD_UNCHANGED', 'new_password');
        }

        $user->forceFill(['password' => $newPassword])->save();

        $this->tokens->revokeAll($user, $this->tokens->familyOf($current));
    }
}

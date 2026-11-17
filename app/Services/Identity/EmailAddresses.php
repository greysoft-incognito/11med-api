<?php

namespace App\Services\Identity;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Identity\EmailCode;

/**
 * An app account's optional email: unconfirmed until its code comes back, and never a way
 * into the account (app handover §3.4).
 */
class EmailAddresses
{
    public function __construct(
        private readonly OneTimeCodes $codes,
    ) {}

    /**
     * Set or remove the address. The same address in any case changes nothing and sends nothing.
     */
    public function update(User $user, ?string $email): User
    {
        if (mb_strtolower((string) $email) === mb_strtolower((string) $user->email)) {
            return $user;
        }

        $taken = $email !== null && User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->whereKeyNot($user->id)
            ->exists();

        if ($taken) {
            throw ApiException::conflict('EMAIL_TAKEN', 'email');
        }

        $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();

        if ($email !== null) {
            $sent = $this->codes->issue(self::target($user));
            $user->notify(new EmailCode($sent->code, intdiv($sent->expiresIn, 60)));
        }

        return $user;
    }

    public function verify(User $user, string $code): User
    {
        $this->requireEmail($user);

        if ($user->email_verified_at !== null) {
            return $user;
        }

        $this->codes->consume(self::target($user), $code);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function resend(User $user): CodeSent
    {
        $this->requireEmail($user);

        if ($user->email_verified_at !== null) {
            throw ApiException::unprocessable('EMAIL_VERIFIED');
        }

        $sent = $this->codes->resend(self::target($user));
        $user->notify(new EmailCode($sent->code, intdiv($sent->expiresIn, 60)));

        return $sent;
    }

    private function requireEmail(User $user): void
    {
        if ($user->email === null) {
            throw ApiException::unprocessable('NO_EMAIL');
        }
    }

    private static function target(User $user): string
    {
        return 'email:' . $user->id;
    }
}

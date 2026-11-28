<?php

namespace App\Services\Identity;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Identity\EmailCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * A portal account's own details: its name, and the email it signs in with, which moves only
 * once a code sent to the new address comes back.
 */
class PortalProfile
{
    public function __construct(
        private readonly OneTimeCodes $codes,
    ) {}

    public function rename(User $account, string $name): User
    {
        $account->forceFill(['full_name' => $name])->save();

        return $account;
    }

    public function requestEmailChange(User $account, string $email, string $password): CodeSent
    {
        if ($account->password === null || ! Hash::check($password, $account->password)) {
            throw ApiException::unprocessable('PASSWORD_INCORRECT', 'password');
        }

        if (mb_strtolower($email) === mb_strtolower((string) $account->email)) {
            throw ApiException::unprocessable('EMAIL_UNCHANGED', 'email');
        }

        $this->refuseTaken($account, $email);

        $sent = $this->codes->resend(self::target($account));

        $account->forceFill(['pending_email' => $email])->save();

        Notification::route('mail', $email)->notify(new EmailCode($sent->code, intdiv($sent->expiresIn, 60)));

        return $sent;
    }

    public function confirmEmailChange(User $account, string $code): User
    {
        $email = $account->pending_email ?? throw ApiException::unprocessable('EMAIL_CHANGE_NOT_STARTED');

        $this->codes->consume(self::target($account), $code);

        DB::transaction(function () use ($account, $email) {
            $this->refuseTaken($account, $email);

            $account->forceFill([
                'email' => $email,
                'email_verified_at' => now(),
                'pending_email' => null,
            ])->save();
        });

        return $account;
    }

    private function refuseTaken(User $account, string $email): void
    {
        $taken = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->whereKeyNot($account->id)
            ->exists();

        if ($taken) {
            throw ApiException::conflict('EMAIL_TAKEN', 'email');
        }
    }

    private static function target(User $account): string
    {
        return 'email-change:' . $account->uuid;
    }
}

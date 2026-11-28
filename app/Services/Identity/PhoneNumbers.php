<?php

namespace App\Services\Identity;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Identity\PhoneCode;
use App\Support\PhoneNumber;
use App\Support\Sms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Moving an app account to a new phone number: the password proves the owner, a code sent to the
 * new number proves it is theirs, and the old number is free for anyone once the move is done.
 */
class PhoneNumbers
{
    public function __construct(
        private readonly AppAccounts $accounts,
        private readonly OneTimeCodes $codes,
    ) {}

    public function requestChange(User $user, string $phone, string $password): CodeSent
    {
        if ($user->password === null || ! Hash::check($password, $user->password)) {
            throw ApiException::unprocessable('PASSWORD_INCORRECT', 'password');
        }

        $phone = PhoneNumber::normalise($phone);

        if ($phone === $user->phone) {
            throw ApiException::unprocessable('PHONE_UNCHANGED', 'phone_number');
        }

        $this->refuseTaken($user, $phone);

        $sent = $this->codes->resend(self::target($user));

        $user->forceFill(['pending_phone' => $phone])->save();

        Sms::to($phone)->notify(new PhoneCode($sent->code, intdiv($sent->expiresIn, 60)));

        return $sent;
    }

    public function confirm(User $user, string $code): User
    {
        $phone = $user->pending_phone ?? throw ApiException::unprocessable('PHONE_CHANGE_NOT_STARTED');

        $this->codes->consume(self::target($user), $code);

        DB::transaction(function () use ($user, $phone) {
            $this->refuseTaken($user, $phone);
            $this->accounts->releaseUnverified($phone);

            $user->forceFill([
                'phone' => $phone,
                'phone_verified_at' => now(),
                'pending_phone' => null,
            ])->save();
        });

        return $user;
    }

    public static function target(User $user): string
    {
        return 'phone-change:' . $user->uuid;
    }

    private function refuseTaken(User $user, string $phone): void
    {
        $taken = User::query()
            ->where('phone', $phone)
            ->whereKeyNot($user->id)
            ->whereNotNull('phone_verified_at')
            ->exists();

        if ($taken) {
            throw ApiException::conflict('PHONE_ALREADY_REGISTERED', 'phone_number');
        }
    }
}

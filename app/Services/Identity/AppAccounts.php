<?php

namespace App\Services\Identity;

use App\Enums\AuthClient;
use App\Enums\ConsentContext;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Notifications\Identity\PhoneCode;
use App\Services\Identity\TwoFactor\TwoFactorChallenges;
use App\Support\PhoneNumber;
use App\Support\Sms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * App accounts: registration by phone, the phone code, sign-in with lockouts, sign-out.
 */
class AppAccounts
{
    public function __construct(
        private readonly OneTimeCodes $codes,
        private readonly LoginGuard $guard,
        private readonly SessionTokens $tokens,
        private readonly TwoFactorChallenges $twoFactor,
        private readonly PolicyConsent $policy,
    ) {}

    /**
     * Create an account waiting on its phone code. A number held by an account that never
     * verified it is taken over, so an abandoned sign-up cannot block its owner.
     *
     * @param  array{full_name: string, phone_number: string, password: ?string, role: string, consent?: array{policy_version: string, accepted_at: string}}  $data
     */
    public function register(array $data, ConsentContext $context = ConsentContext::Register): PendingAccount
    {
        $phone = PhoneNumber::normalise($data['phone_number']);

        $user = DB::transaction(function () use ($data, $phone, $context) {
            $this->releaseUnverified($phone);

            $user = User::create([
                'full_name' => $data['full_name'],
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
            ]);

            $user->forceFill(['email_verified_at' => $data['email_verified_at'] ?? null])->save();
            $user->assignRole($data['role']);

            ConsentRecord::create([
                'user_id' => $user->id,
                'context' => $context,
                'policy_version' => $data['consent']['policy_version'] ?? $this->policy->recordable(Role::from($data['role'])),
                'accepted_at' => $data['consent']['accepted_at'] ?? now(),
                'recorded_at' => now(),
            ]);

            return $user;
        });

        $sent = $this->sendCode($phone);

        return new PendingAccount($user, $sent->expiresIn);
    }

    public function verifyPhone(string $phone, string $code, ?string $installId): AppSession
    {
        $user = $this->findByPhone(PhoneNumber::normalise($phone))
            ?? throw ApiException::notFound('USER_NOT_FOUND', 'phone_number');

        $this->codes->consume(self::target($user->phone), $code);

        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now()])->save();

        $user->socialIdentities()
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => now()]);

        return $this->open($user, $installId);
    }

    /**
     * Another phone code within the budget. The answer is the same whether or not the number
     * has an account; only an account's number is texted.
     */
    public function resendCode(string $phone): CodeSent
    {
        $phone = PhoneNumber::normalise($phone);
        $sent = $this->codes->resend(self::target($phone));

        if ($this->findByPhone($phone) !== null) {
            Sms::to($phone)->notify(new PhoneCode($sent->code, intdiv($sent->expiresIn, 60)));
        }

        return $sent;
    }

    public function login(string $phone, string $password, ?string $installId): AppSession
    {
        $phone = PhoneNumber::normalise($phone);

        $this->guard->check($phone);

        $user = $this->findByPhone($phone);

        if ($user?->password === null || ! Hash::check($password, $user->password)) {
            $this->guard->fail($phone);
        }

        $this->guard->clear($phone);

        if ($user->phone_verified_at === null) {
            $this->sendCode($phone);

            throw ApiException::forbidden('PHONE_NOT_VERIFIED');
        }

        return $this->open($user, $installId);
    }

    /**
     * Sign the account in, or ask for its second step when two-step verification is on.
     */
    public function open(User $user, ?string $installId): AppSession
    {
        $installId = $installId !== null && Str::isUuid($installId) ? $installId : null;

        $this->twoFactor->requireFor($user, AuthClient::App, $installId);

        $pair = $this->tokens->issue($user, AuthClient::App, $installId);

        return new AppSession($user, $pair);
    }

    /**
     * The live app account holding a phone number.
     */
    public function findByPhone(string $phone): ?User
    {
        return User::query()
            ->where('phone', $phone)
            ->whereHas('roles', fn($q) => $q->whereIn('name', Role::values(Role::app())))
            ->first();
    }

    public function sendCode(string $phone): CodeSent
    {
        $sent = $this->codes->issue(self::target($phone));

        Sms::to($phone)->notify(new PhoneCode($sent->code, intdiv($sent->expiresIn, 60)));

        return $sent;
    }

    public static function target(string $phone): string
    {
        return 'phone:' . $phone;
    }

    /**
     * Refuse a number a verified account holds; remove one an unverified sign-up left behind.
     */
    public function releaseUnverified(string $phone): void
    {
        $holder = User::where('phone', $phone)->first();

        if ($holder === null) {
            return;
        }

        if ($holder->phone_verified_at !== null) {
            throw ApiException::conflict('PHONE_ALREADY_REGISTERED', 'phone_number');
        }

        $holder->tokens()->delete();
        $holder->forceDelete();
    }
}

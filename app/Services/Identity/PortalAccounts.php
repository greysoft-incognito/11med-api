<?php

namespace App\Services\Identity;

use App\Enums\AccountKind;
use App\Enums\AuthClient;
use App\Exceptions\ApiException;
use App\Models\PasswordLink;
use App\Models\User;
use App\Notifications\Identity\PortalAccessGranted;
use App\Notifications\Identity\PortalPasswordLink;
use App\Services\Identity\TwoFactor\TwoFactorChallenges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Portal sign-in (pharmacy, laboratory, the team): email and password, no OTP. The account may also
 * hold app kinds; the portal session reaches only portal routes. Passwords are set from emailed
 * links, for a reset or a new account.
 */
class PortalAccounts
{
    public function __construct(
        private readonly SessionTokens $tokens,
        private readonly TwoFactorChallenges $twoFactor,
    ) {}

    public function signIn(string $email, string $password): PortalSession
    {
        $account = $this->findByEmail($email);

        if ($account?->password === null || ! Hash::check($password, $account->password)) {
            throw ApiException::unauthorized('INVALID_CREDENTIALS');
        }

        return $this->open($account);
    }

    public function refresh(string $refreshToken): PortalSession
    {
        [$account, $pair] = $this->tokens->rotate($refreshToken, AuthClient::Portal);

        return new PortalSession($account, $pair);
    }

    /**
     * Ends the session the bearer token belongs to, expired or not.
     */
    public function signOut(?string $bearer): void
    {
        $token = $bearer === null ? null : PersonalAccessToken::findToken($bearer);

        if ($token !== null) {
            $this->tokens->revokeByAccessToken($token);
        }
    }

    /**
     * Email a reset link to a portal account. The caller's answer is the same either way.
     */
    public function forgot(string $email): void
    {
        $account = $this->findByEmail($email);

        if ($account !== null) {
            $this->sendLink($account, PasswordLink::RESET);
        }
    }

    /**
     * Email a new account its first-password link, retiring any earlier unused one; returns the link.
     */
    public function welcome(User $account): string
    {
        PasswordLink::query()
            ->where('user_id', $account->id)
            ->where('kind', PasswordLink::WELCOME)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return $this->sendLink($account, PasswordLink::WELCOME);
    }

    /**
     * Set the password from a link, end every other session, and sign the person in.
     */
    public function setPassword(string $token, string $password): PortalSession
    {
        $account = DB::transaction(function () use ($token, $password) {
            $link = PasswordLink::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($link === null || $link->used_at !== null || $link->expires_at->isPast() || $link->user === null) {
                throw ApiException::unprocessable('RESET_INVALID', 'token');
            }

            $link->update(['used_at' => now()]);

            $link->user->forceFill([
                'password' => $password,
                'email_verified_at' => $link->user->email_verified_at ?? now(),
            ])->save();

            return $link->user;
        });

        $this->tokens->revokeAll($account);

        return $this->open($account);
    }

    /**
     * An account that may sign in to the portals: a pharmacy or laboratory member, or a team
     * member, deactivated or not.
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->where(fn($query) => $query
                ->ofKind(AccountKind::portal())
                ->orWhereNotNull('staff_since'))
            ->first();
    }

    /**
     * The account a portal invitation goes to: a new one, or the person's existing account when
     * its email is verified, so nobody inherits access by typing another person's address. The
     * second value says whether it already existed.
     *
     * @return array{0: User, 1: bool}
     */
    public function openOrLink(string $email, string $name, string $field): array
    {
        $existing = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->first();

        if ($existing === null) {
            $account = User::create([
                'full_name' => $name,
                'email' => mb_strtolower($email),
            ]);

            return [$account, false];
        }

        if ($existing->email_verified_at === null) {
            throw ApiException::conflict('EMAIL_TAKEN', $field);
        }

        return [$existing, true];
    }

    /**
     * Tells an existing account it was given access to a portal; it signs in as it already does.
     */
    public function announceAccess(User $account, string $place): void
    {
        $url = rtrim(config('elevenmed.portals_url'), '/') . '/sign-in';

        $account->notify(new PortalAccessGranted($place, $url));
    }

    private function open(User $account): PortalSession
    {
        if (! $account->holds(...AccountKind::portal()) && ! $account->isStaff()) {
            throw ApiException::forbidden('ACCOUNT_DEACTIVATED');
        }
        $this->twoFactor->requireFor($account, AuthClient::Portal);

        $pair = $this->tokens->issue($account, AuthClient::Portal);

        return new PortalSession($account, $pair);
    }

    private function sendLink(User $account, string $kind): string
    {
        $token = Str::random(48);
        $welcome = $kind === PasswordLink::WELCOME;

        PasswordLink::create([
            'user_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'kind' => $kind,
            'expires_at' => $welcome ? now()->addWeek() : now()->addHour(),
        ]);

        $url = rtrim(config('elevenmed.portals_url'), '/') . '/set-password?token=' . $token;

        $account->notify(new PortalPasswordLink($url, $welcome));

        return $url;
    }
}

<?php

namespace App\Services\Identity\Social;

use App\Enums\ConsentContext;
use App\Enums\SocialProvider;
use App\Exceptions\ApiException;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\Identity\AppAccounts;
use App\Services\Identity\PendingAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Google and Apple sign-in (app spec §2.2). A new identity still needs a verified Nigerian
 * phone, so it gets a link token first; the identity is confirmed with the phone code.
 */
class SocialAccounts
{
    private const LINK_MINUTES = 15;

    public function __construct(
        private readonly IdentityTokenVerifier $verifier,
        private readonly AppAccounts $accounts,
    ) {}

    public function signIn(SocialProvider $provider, string $idToken, ?string $installId, ?string $fullName): SocialOutcome
    {
        $claims = $this->verifier->verify($provider, $idToken);

        $identity = SocialIdentity::query()
            ->where('provider', $provider)
            ->where('subject', $claims->subject)
            ->whereNotNull('confirmed_at')
            ->with('user')
            ->first();

        if ($identity?->user !== null) {
            return SocialOutcome::authenticated($this->accounts->open($identity->user, $installId));
        }

        $token = Str::random(48);

        $link = [
            'provider' => $provider->value,
            'subject' => $claims->subject,
            'email' => $claims->email,
            'emailVerified' => $claims->emailVerified,
        ];

        Cache::put(self::key($token), $link, now()->addMinutes(self::LINK_MINUTES));

        return SocialOutcome::phoneRequired($token, $fullName ?? $claims->name);
    }

    /**
     * Create the account for a new identity, waiting on its phone code.
     *
     * @param  array{link_token: string, phone_number: string, full_name: string, role: string, consent?: array{policy_version: string, accepted_at: string}}  $data
     */
    public function linkPhone(array $data): PendingAccount
    {
        $claims = Cache::get(self::key($data['link_token']))
            ?? throw ApiException::unauthorized('AUTH_SESSION_EXPIRED');

        $email = $claims['email'] !== null && ! $this->emailTaken($claims['email']) ? $claims['email'] : null;

        $pending = DB::transaction(function () use ($data, $claims, $email) {
            $pending = $this->accounts->register([
                ...$data,
                'password' => null,
                'email' => $email,
                'email_verified_at' => $email !== null && $claims['emailVerified'] ? now() : null,
            ], ConsentContext::SocialLink);

            SocialIdentity::query()
                ->where('provider', $claims['provider'])
                ->where('subject', $claims['subject'])
                ->whereNull('confirmed_at')
                ->forceDelete();

            SocialIdentity::create([
                'user_id' => $pending->user->id,
                'provider' => $claims['provider'],
                'subject' => $claims['subject'],
            ]);

            return $pending;
        });

        Cache::forget(self::key($data['link_token']));

        return $pending;
    }

    private function emailTaken(string $email): bool
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->exists();
    }

    private static function key(string $token): string
    {
        return 'social-link:' . hash('sha256', $token);
    }
}

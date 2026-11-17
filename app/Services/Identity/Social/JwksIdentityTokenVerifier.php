<?php

namespace App\Services\Identity\Social;

use App\Enums\SocialProvider;
use App\Exceptions\ApiException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks an ID token's signature against the provider's published keys (cached an hour), its
 * issuer, its audience (the Google web client id, the Apple bundle id) and its expiry.
 */
class JwksIdentityTokenVerifier implements IdentityTokenVerifier
{
    public function verify(SocialProvider $provider, string $idToken): IdentityClaims
    {
        $audience = $provider->audience();

        if ($audience === null || $audience === '') {
            throw ApiException::unprocessable('SOCIAL_UNAVAILABLE', 'provider');
        }

        try {
            $claims = JWT::decode($idToken, JWK::parseKeySet($this->keys($provider), 'RS256'));
        } catch (Throwable) {
            throw ApiException::unauthorized('SOCIAL_TOKEN_INVALID');
        }

        $audiences = (array) ($claims->aud ?? []);

        if (! in_array($claims->iss ?? null, $provider->issuers(), true) || ! in_array($audience, $audiences, true) || ! isset($claims->sub)) {
            throw ApiException::unauthorized('SOCIAL_TOKEN_INVALID');
        }

        return new IdentityClaims(
            $provider,
            (string) $claims->sub,
            isset($claims->email) ? (string) $claims->email : null,
            in_array($claims->email_verified ?? false, [true, 'true'], true),
            isset($claims->name) ? (string) $claims->name : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function keys(SocialProvider $provider): array
    {
        return Cache::remember(
            "social-keys:{$provider->value}",
            now()->addHour(),
            fn() => Http::timeout(5)
                ->get($provider->keysUrl())
                ->throw()
                ->json()
        );
    }
}

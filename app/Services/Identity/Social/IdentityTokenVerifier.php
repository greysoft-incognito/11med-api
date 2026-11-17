<?php

namespace App\Services\Identity\Social;

use App\Enums\SocialProvider;
use Illuminate\Container\Attributes\Bind;

#[Bind(JwksIdentityTokenVerifier::class)]
interface IdentityTokenVerifier
{
    /**
     * The token's claims, or a 401 `SOCIAL_TOKEN_INVALID`.
     */
    public function verify(SocialProvider $provider, string $idToken): IdentityClaims;
}

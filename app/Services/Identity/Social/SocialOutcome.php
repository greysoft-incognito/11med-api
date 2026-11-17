<?php

namespace App\Services\Identity\Social;

use App\Services\Identity\AppSession;

/**
 * `zSocialAuthResponse`: a session for a known identity, or a link token to bring a phone.
 */
final readonly class SocialOutcome
{
    private function __construct(
        public ?AppSession $session,
        public ?string $linkToken,
        public ?string $suggestedFullName,
    ) {}

    public static function authenticated(AppSession $session): self
    {
        return new self($session, null, null);
    }

    public static function phoneRequired(string $linkToken, ?string $suggestedFullName): self
    {
        return new self(null, $linkToken, $suggestedFullName);
    }
}

<?php

namespace App\Services\Identity\Social;

use App\Enums\SocialProvider;

/**
 * What a verified ID token says about the person.
 */
final readonly class IdentityClaims
{
    public function __construct(
        public SocialProvider $provider,
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}
}

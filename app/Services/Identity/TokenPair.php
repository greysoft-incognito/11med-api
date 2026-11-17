<?php

namespace App\Services\Identity;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $family,
    ) {}
}

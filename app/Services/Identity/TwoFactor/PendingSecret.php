<?php

namespace App\Services\Identity\TwoFactor;

final readonly class PendingSecret
{
    public function __construct(
        public string $secret,
        public string $otpauthUrl,
    ) {}
}

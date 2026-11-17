<?php

namespace App\Services\Identity;

/**
 * A code went out: how long it lives and how many more may be sent in the window.
 */
final readonly class CodeSent
{
    public function __construct(
        public string $code,
        public int $expiresIn,
        public int $resendsRemaining,
    ) {}
}

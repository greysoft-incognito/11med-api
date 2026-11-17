<?php

namespace App\Services\Identity;

use App\Models\User;

final readonly class PortalSession
{
    public function __construct(
        public User $account,
        public TokenPair $tokens,
    ) {}
}

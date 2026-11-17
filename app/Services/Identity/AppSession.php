<?php

namespace App\Services\Identity;

use App\Models\User;

final readonly class AppSession
{
    public function __construct(
        public User $user,
        public TokenPair $tokens,
    ) {}
}

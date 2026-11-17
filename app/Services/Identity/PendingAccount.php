<?php

namespace App\Services\Identity;

use App\Models\User;

/**
 * An account waiting on its phone code.
 */
final readonly class PendingAccount
{
    public function __construct(
        public User $user,
        public int $otpExpiresIn,
    ) {}
}

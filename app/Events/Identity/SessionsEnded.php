<?php

namespace App\Events\Identity;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sessions ended on the server's side: a revoked family, a password change, a reset.
 * The installs named can no longer clean up after themselves.
 */
class SessionsEnded
{
    use Dispatchable;

    /**
     * @param  list<string>  $installIds
     */
    public function __construct(
        public readonly User $user,
        public readonly array $installIds,
    ) {}
}

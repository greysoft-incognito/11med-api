<?php

namespace App\Events\Identity;

use App\Models\SignIn;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An account signed in on a device it had not used before.
 */
class NewSignIn implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly SignIn $signIn,
    ) {}
}

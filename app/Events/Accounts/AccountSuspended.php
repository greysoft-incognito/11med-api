<?php

namespace App\Events\Accounts;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The team suspended a patient or doctor account; its sessions are already ended.
 */
class AccountSuspended
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
    ) {}
}

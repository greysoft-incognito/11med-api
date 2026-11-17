<?php

namespace App\Events\Identity;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An account is about to be erased, inside the erasing transaction. Each module that holds
 * the person's data, or relationships naming them on someone else's records, removes it here.
 */
class AccountErasing
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
    ) {}
}

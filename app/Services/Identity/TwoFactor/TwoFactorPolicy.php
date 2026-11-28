<?php

namespace App\Services\Identity\TwoFactor;

use App\Models\User;

/**
 * Who must have two-step verification: anyone with staff access, while ADMIN_2FA_REQUIRED is on.
 */
class TwoFactorPolicy
{
    public function required(User $user): bool
    {
        return $user->isStaff() && (bool) config('elevenmed.two_factor.admin_required');
    }

    public function missing(User $user): bool
    {
        return $this->required($user) && ! $user->hasTwoFactor();
    }
}

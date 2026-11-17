<?php

namespace App\Services\Subscriptions;

use App\Models\User;

/**
 * The free-tier gates (app CLAUDE.md "Free vs premium gates"), in one place. `is_premium` comes
 * from the subscription record; M5 adds the remaining gates here.
 */
class Entitlements
{
    public function caregiverLimit(User $user): int
    {
        return config($user->isPremium() ? 'elevenmed.caregivers.premium' : 'elevenmed.caregivers.free');
    }
}

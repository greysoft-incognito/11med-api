<?php

namespace App\Services\Subscriptions;

use App\Exceptions\ApiException;
use App\Models\User;

/**
 * The free-tier gates (app CLAUDE.md "Free vs premium gates"), in one place. `is_premium` comes
 * from the subscription record (M5); every other module asks here rather than reading it.
 */
class Entitlements
{
    public function caregiverLimit(User $user): int
    {
        return config($user->isPremium() ? 'elevenmed.caregivers.premium' : 'elevenmed.caregivers.free');
    }

    /**
     * Active medications a user may hold, or null for no limit. Prescribed medicines are not counted.
     */
    public function medicationLimit(User $user): ?int
    {
        return $user->isPremium() ? null : config('elevenmed.free_tier.medications');
    }

    /**
     * Days of dose history a user may read back, or null for all of it.
     */
    public function historyDays(User $user): ?int
    {
        return $user->isPremium() ? null : config('elevenmed.free_tier.history_days');
    }

    /**
     * Days of readings a user may read back, or null for all of them.
     */
    public function readingsDays(User $user): ?int
    {
        return $user->isPremium() ? null : config('elevenmed.free_tier.readings_days');
    }

    /**
     * Refuses a premium-only feature (the compliance report, insights) with 403 `PREMIUM_REQUIRED`.
     */
    public function requirePremium(User $user): void
    {
        if (! $user->isPremium()) {
            throw ApiException::forbidden('PREMIUM_REQUIRED');
        }
    }
}

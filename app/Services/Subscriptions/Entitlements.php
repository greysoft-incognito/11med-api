<?php

namespace App\Services\Subscriptions;

use App\Exceptions\ApiException;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Every free-tier gate (app CLAUDE.md "Free vs premium gates"), in one place. Premium comes from
 * the subscription record; a null limit means unlimited.
 */
class Entitlements
{
    public function caregiverLimit(User $user): int
    {
        return config($user->isPremium() ? 'elevenmed.caregivers.premium' : 'elevenmed.caregivers.free');
    }

    /**
     * Active medications the account may hold. Prescribed ones are not counted against it.
     */
    public function medicationLimit(User $user): ?int
    {
        return $user->isPremium() ? null : config('elevenmed.free_tier.medications');
    }

    /**
     * Refuses a new medication over the cap with 422 `MEDICATION_LIMIT_REACHED`. A medication
     * prescribed through the app is exempt (app spec §13 item 7).
     */
    public function ensureCanAddMedication(User $user, int $active, bool $prescribed = false): void
    {
        $limit = $this->medicationLimit($user);

        if ($prescribed || $limit === null || $active < $limit) {
            return;
        }

        throw ApiException::unprocessable('MEDICATION_LIMIT_REACHED', null, ['limit' => $limit, 'active' => $active]);
    }

    /**
     * Days of dose history and compliance the account may read.
     */
    public function historyDays(User $user): ?int
    {
        return $user->isPremium() ? null : config('elevenmed.free_tier.history_days');
    }

    /**
     * Days of readings the account may read.
     */
    public function readingsDays(User $user): ?int
    {
        return $user->isPremium() ? null : config('elevenmed.free_tier.readings_days');
    }

    /**
     * The first Lagos day of history the account may read, or null for all of it.
     */
    public function historyFrom(User $user): ?CarbonImmutable
    {
        return $this->windowStart($this->historyDays($user));
    }

    /**
     * The first Lagos day of readings the account may read, or null for all of them.
     */
    public function readingsFrom(User $user): ?CarbonImmutable
    {
        return $this->windowStart($this->readingsDays($user));
    }

    /**
     * Refuses a premium-only feature (the report, insights) with 403 `PREMIUM_REQUIRED`.
     */
    public function requirePremium(User $user): void
    {
        if (! $user->isPremium()) {
            throw ApiException::forbidden('PREMIUM_REQUIRED');
        }
    }

    private function windowStart(?int $days): ?CarbonImmutable
    {
        if ($days === null) {
            return null;
        }

        return CarbonImmutable::parse(Subscription::today(), config('elevenmed.timezone'))->subDays($days - 1);
    }
}

<?php

namespace App\Services\Identity;

use App\Enums\ConsentContext;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Models\ConsentRecord;
use App\Models\User;

/**
 * The terms and privacy policy in force (`elevenmed.consent.policy_version`): whether an account
 * has accepted that version, and the acceptance when it has not. The team's accounts are not asked.
 */
class PolicyConsent
{
    public function current(): string
    {
        return (string) config('elevenmed.consent.policy_version');
    }

    /**
     * The version the account must still accept, or null when it has.
     */
    public function pendingFor(User $user): ?string
    {
        if ($user->role() === Role::Admin) {
            return null;
        }

        $version = $this->current();

        $accepted = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('policy_version', $version)
            ->exists();

        return $accepted ? null : $version;
    }

    public function accept(User $user, string $version): User
    {
        if ($version !== $this->current()) {
            throw ApiException::unprocessable('POLICY_VERSION_OUTDATED', 'policy_version');
        }

        if ($this->pendingFor($user) !== null) {
            ConsentRecord::create([
                'user_id' => $user->id,
                'context' => ConsentContext::PolicyUpdate,
                'policy_version' => $version,
                'accepted_at' => now(),
                'recorded_at' => now(),
            ]);
        }

        return $user;
    }
}

<?php

namespace App\Services\Identity;

use App\Enums\ConsentContext;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Models\ConsentRecord;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\Documents;

/**
 * The terms and privacy policy in force for an account: the versions of the pair it reads, joined
 * (`terms.privacy`, `2.1`), so every edit to either asks again. App accounts read `terms` and
 * `privacy`, partner accounts the partner pair. The team's accounts are not asked.
 */
class PolicyConsent
{
    public const UNPUBLISHED = 'unpublished';

    public function __construct(
        private readonly Documents $documents,
    ) {}

    /**
     * The version in force for a role, or null for the team or while its documents are unwritten.
     */
    public function current(?Role $role): ?string
    {
        $slugs = match ($role) {
            Role::Patient, Role::Doctor => Document::APP,
            Role::Pharmacy, Role::Laboratory => Document::PARTNER,
            default => null,
        };

        return $slugs === null ? null : $this->documents->policyVersion($slugs);
    }

    /**
     * The version a consent given now is recorded against: the one in force, or `unpublished`
     * while the role's documents are unwritten.
     */
    public function recordable(?Role $role): string
    {
        return $this->current($role) ?? self::UNPUBLISHED;
    }

    /**
     * The version the account must still accept, or null when it has.
     */
    public function pendingFor(User $user): ?string
    {
        $version = $this->current($user->role());

        if ($version === null) {
            return null;
        }

        $accepted = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('policy_version', $version)
            ->exists();

        return $accepted ? null : $version;
    }

    public function accept(User $user, string $version): User
    {
        if ($version !== $this->current($user->role())) {
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

<?php

namespace App\Services\Identity;

use App\Enums\AccountKind;
use App\Enums\AuthClient;
use App\Enums\ConsentContext;
use App\Exceptions\ApiException;
use App\Models\ConsentRecord;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\Documents;

/**
 * The terms and privacy policy in force for an account: the versions of the pair it reads, joined
 * (`terms.privacy`, `2.1`), so every edit to either asks again. The app asks a patient or doctor
 * for `terms` and `privacy`, a partner portal asks its members for the partner pair, and staff
 * access alone is not asked.
 */
class PolicyConsent
{
    public const UNPUBLISHED = 'unpublished';

    public function __construct(
        private readonly Documents $documents,
    ) {}

    /**
     * The version in force for a kind, or null while its documents are unwritten.
     */
    public function current(?AccountKind $kind): ?string
    {
        $slugs = match ($kind) {
            AccountKind::Patient, AccountKind::Doctor => Document::APP,
            AccountKind::Pharmacy, AccountKind::Laboratory => Document::PARTNER,
            default => null,
        };

        return $slugs === null ? null : $this->documents->policyVersion($slugs);
    }

    /**
     * The version in force for the account on one client, or null when that client asks it nothing.
     */
    public function currentFor(User $user, AuthClient $client): ?string
    {
        $held = $user->kindsOf($client === AuthClient::App ? AccountKind::app() : AccountKind::portal());

        return $this->current($held[0] ?? null);
    }

    /**
     * The version a consent given now is recorded against: the one in force, or `unpublished`
     * while the kind's documents are unwritten.
     */
    public function recordable(?AccountKind $kind): string
    {
        return $this->current($kind) ?? self::UNPUBLISHED;
    }

    /**
     * The version the account must still accept, or null when it has.
     */
    public function pendingFor(User $user, AuthClient $client): ?string
    {
        $version = $this->currentFor($user, $client);

        if ($version === null) {
            return null;
        }

        $accepted = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('policy_version', $version)
            ->exists();

        return $accepted ? null : $version;
    }

    public function accept(User $user, AuthClient $client, string $version): User
    {
        if ($version !== $this->currentFor($user, $client)) {
            throw ApiException::unprocessable('POLICY_VERSION_OUTDATED', 'policy_version');
        }

        if ($this->pendingFor($user, $client) !== null) {
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

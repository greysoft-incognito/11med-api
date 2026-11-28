<?php

namespace App\Http\Resources\Identity;

use App\Enums\AccountKind;
use App\Enums\AuthClient;
use App\Http\Resources\ApiResource;
use App\Models\User;
use App\Services\Identity\PolicyConsent;
use App\Services\Identity\TwoFactor\TwoFactorPolicy;
use App\Services\Staff\StaffAccess;
use Illuminate\Http\Request;

/**
 * `zPortalAccount`. `kinds` are the partner portals the account belongs to, with its organisation,
 * whose owner manages the partner's staff; `permissions` are the admin permissions it holds, and
 * any at all opens the admin portal.
 *
 * @property User $resource
 */
class PortalAccountResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource;
        $organisation = $account->organisation;
        $permissions = app(StaffAccess::class)->permissionsOf($account);

        return [
            'id' => $account->uuid,
            'email' => $account->email,
            'name' => $account->full_name,
            'kinds' => AccountKind::values($account->kindsOf(AccountKind::portal())),
            'organisation_id' => $organisation?->uuid,
            'organisation_name' => $organisation?->name,
            'is_owner' => $organisation !== null && $organisation->owner_id === $account->id,
            'pending_policy_version' => app(PolicyConsent::class)->pendingFor($account, AuthClient::Portal),
            'permissions' => $permissions,
            'created_at' => $this->iso($account->created_at),
            'two_factor_enabled' => $account->hasTwoFactor(),
            'two_factor_required' => app(TwoFactorPolicy::class)->required($account),
        ];
    }
}

<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Models\User;
use App\Services\Identity\PolicyConsent;
use App\Services\Identity\TwoFactor\TwoFactorPolicy;
use App\Services\Staff\StaffAccess;
use Illuminate\Http\Request;

/**
 * `zPortalAccount`. A pharmacy's or laboratory's account carries its organisation, and its owner manages
 * the partner's staff; the team's carries none and lists the admin permissions it holds.
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
            'role' => $account->role()?->value,
            'organisation_id' => $organisation?->uuid,
            'organisation_name' => $organisation?->name,
            'is_owner' => $organisation !== null && $organisation->owner_id === $account->id,
            'pending_policy_version' => app(PolicyConsent::class)->pendingFor($account),
            'permissions' => $permissions,
            'created_at' => $this->iso($account->created_at),
            'two_factor_enabled' => $account->hasTwoFactor(),
            'two_factor_required' => app(TwoFactorPolicy::class)->required($account),
        ];
    }
}

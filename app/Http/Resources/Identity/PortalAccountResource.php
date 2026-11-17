<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * `zPortalAccount`. A pharmacy's or laboratory's account carries its organisation; the team's carries none.
 *
 * @property User $resource
 */
class PortalAccountResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource;
        $organisation = $account->organisation;

        return [
            'id' => $account->uuid,
            'email' => $account->email,
            'name' => $account->full_name,
            'role' => $account->role()?->value,
            'organisation_id' => $organisation?->uuid,
            'organisation_name' => $organisation?->name,
            'created_at' => $this->iso($account->created_at),
        ];
    }
}

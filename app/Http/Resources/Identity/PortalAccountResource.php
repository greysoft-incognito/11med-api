<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * `zPortalAccount`. The organisation is null until the Partners module (M11) links one.
 *
 * @property User $resource
 */
class PortalAccountResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource;

        return [
            'id' => $account->uuid,
            'email' => $account->email,
            'name' => $account->full_name,
            'role' => $account->role()?->value,
            'organisation_id' => null,
            'organisation_name' => null,
            'created_at' => $this->iso($account->created_at),
        ];
    }
}

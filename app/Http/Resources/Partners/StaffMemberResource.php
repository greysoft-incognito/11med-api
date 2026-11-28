<?php

namespace App\Http\Resources\Partners;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * `zStaffMember`: invited until the member sets a password from the welcome link.
 *
 * @property User $resource
 */
class StaffMemberResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $member = $this->resource;

        return [
            'id' => $member->uuid,
            'name' => $member->full_name,
            'email' => $member->email,
            'is_owner' => $member->organisation?->owner_id === $member->id,
            'status' => $member->password === null ? 'invited' : 'active',
            'created_at' => $this->iso($member->created_at),
        ];
    }
}

<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * `zAccount` (app `src/api/contracts/auth.ts`), the user inside a session.
 *
 * @property User $resource
 */
class AccountResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->uuid,
            'full_name' => $user->full_name,
            'phone_number' => $user->phone,
            'email' => $user->email,
            'email_verified' => $user->email !== null && $user->email_verified_at !== null,
            'role' => $user->role()?->value,
            'has_medical_profile' => $user->hasMedicalProfile(),
            'is_premium' => $user->isPremium(),
        ];
    }
}

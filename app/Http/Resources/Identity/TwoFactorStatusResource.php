<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Models\User;
use App\Services\Identity\TwoFactor\TwoFactorPolicy;
use Illuminate\Http\Request;

/**
 * `zTwoFactorStatus`.
 *
 * @property User $resource
 */
class TwoFactorStatusResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'enabled' => $user->hasTwoFactor(),
            'confirmed_at' => $this->iso($user->two_factor_confirmed_at),
            'recovery_codes_left' => count($user->two_factor_recovery_codes ?? []),
            'required' => app(TwoFactorPolicy::class)->required($user),
        ];
    }
}

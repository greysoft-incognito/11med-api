<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\PendingAccount;
use Illuminate\Http\Request;

/**
 * `zRegisterResponse`, also the answer to linking a phone.
 *
 * @property PendingAccount $resource
 */
class PendingAccountResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource->user->uuid,
            'phone_number' => $this->resource->user->phone,
            'otp_expires_in' => $this->resource->otpExpiresIn,
        ];
    }
}

<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\TwoFactor\PendingSecret;
use Illuminate\Http\Request;

/**
 * `zTwoFactorSetup`: the secret to type in, and the `otpauth://` link the QR code carries.
 *
 * @property PendingSecret $resource
 */
class PendingSecretResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'secret' => $this->resource->secret,
            'otpauth_url' => $this->resource->otpauthUrl,
        ];
    }
}

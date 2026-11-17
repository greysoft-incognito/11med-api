<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\CodeSent;
use Illuminate\Http\Request;

/**
 * `zResendOtpResponse`.
 *
 * @property CodeSent $resource
 */
class OtpSentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'otp_expires_in' => $this->resource->expiresIn,
            'resends_remaining' => $this->resource->resendsRemaining,
        ];
    }
}

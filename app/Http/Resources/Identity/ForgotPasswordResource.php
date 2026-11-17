<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\CodeSent;
use Illuminate\Http\Request;

/**
 * The app's forgot-password answer: how long the code lives.
 *
 * @property CodeSent $resource
 */
class ForgotPasswordResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'otp_expires_in' => $this->resource->expiresIn,
        ];
    }
}

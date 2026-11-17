<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\AppSession;
use Illuminate\Http\Request;

/**
 * `zSession`.
 *
 * @property AppSession $resource
 */
class AppSessionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return [
            'access_token' => $this->resource->tokens->accessToken,
            'refresh_token' => $this->resource->tokens->refreshToken,
            'user' => new AccountResource($user),
        ];
    }
}

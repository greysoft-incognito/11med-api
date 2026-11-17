<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\TokenPair;
use Illuminate\Http\Request;

/**
 * `zRefreshResponse`: the app's new pair.
 *
 * @property TokenPair $resource
 */
class TokenPairResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'refresh_token' => $this->resource->refreshToken,
        ];
    }
}

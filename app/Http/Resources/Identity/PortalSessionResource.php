<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\PortalSession;
use Illuminate\Http\Request;

/**
 * `zPortalSession`.
 *
 * @property PortalSession $resource
 */
class PortalSessionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource->account;

        return [
            'access_token' => $this->resource->tokens->accessToken,
            'refresh_token' => $this->resource->tokens->refreshToken,
            'account' => new PortalAccountResource($account),
        ];
    }
}

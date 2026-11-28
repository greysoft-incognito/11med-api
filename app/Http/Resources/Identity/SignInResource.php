<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Models\SignIn;
use Illuminate\Http\Request;

/**
 * `zSignedInSession`.
 *
 * @property SignIn $resource
 */
class SignInResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->uuid,
            'device_name' => $this->resource->device_name,
            'platform' => $this->resource->platform,
            'signed_in_at' => $this->iso($this->resource->signed_in_at),
            'last_used_at' => $this->iso($this->resource->last_used_at),
            'current' => (bool) $this->resource->getAttribute('current'),
        ];
    }
}

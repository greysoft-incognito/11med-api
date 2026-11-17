<?php

namespace App\Http\Resources\Devices;

use App\Http\Resources\ApiResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `zDevice`.
 *
 * @property Device $resource
 */
class DeviceResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'install_id' => $this->resource->install_id,
            'platform' => $this->resource->platform->value,
            'push_provider' => $this->resource->push_provider->value,
            'registered_at' => $this->iso($this->resource->registered_at),
        ];
    }

    /**
     * An upsert answers 200 whether the row is new or not.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }
}

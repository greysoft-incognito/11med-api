<?php

namespace App\Http\Resources\Devices;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `{ install_id }`, the answer to removing a device whether or not it was held.
 *
 * @property string $resource
 */
class UnregisteredDeviceResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'install_id' => $this->resource,
        ];
    }
}

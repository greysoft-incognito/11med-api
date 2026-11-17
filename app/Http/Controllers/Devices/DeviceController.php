<?php

namespace App\Http\Controllers\Devices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Devices\RegisterDeviceRequest;
use App\Http\Resources\Devices\DeviceResource;
use App\Http\Resources\Devices\UnregisteredDeviceResource;
use App\Services\Devices\Devices;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        private readonly Devices $devices,
    ) {}

    public function update(RegisterDeviceRequest $request, string $installId): DeviceResource
    {
        $device = $this->devices->register($request->user(), $installId, $request->validated());

        return new DeviceResource($device);
    }

    public function destroy(Request $request, string $installId): UnregisteredDeviceResource
    {
        $this->devices->unregister($request->user(), $installId);

        return new UnregisteredDeviceResource($installId);
    }
}

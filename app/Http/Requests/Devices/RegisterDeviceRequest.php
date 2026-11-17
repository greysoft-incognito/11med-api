<?php

namespace App\Http\Requests\Devices;

use App\Enums\Platform;
use App\Enums\PushProvider;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zRegisterDeviceRequest` (app `src/api/contracts/devices.ts`).
 */
class RegisterDeviceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(Platform::class)],
            'push_provider' => ['required', Rule::enum(PushProvider::class)],
            'push_token' => ['required', 'string', 'max:4096'],
            'app_version' => ['required', 'string', 'max:32'],
            'locale' => ['required', 'string', 'max:16'],
        ];
    }
}

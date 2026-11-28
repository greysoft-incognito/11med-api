<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PlatformSetting;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingRequest;
use App\Http\Resources\Settings\PlatformSettingCollection;
use App\Http\Resources\Settings\PlatformSettingResource;
use App\Services\Settings\PlatformSettings;

class AdminSettingController extends Controller
{
    public function __construct(
        private readonly PlatformSettings $settings,
    ) {}

    public function index(): PlatformSettingCollection
    {
        $settings = collect($this->settings->all());

        return new PlatformSettingCollection($settings);
    }

    public function update(UpdateSettingRequest $request, string $key): PlatformSettingResource
    {
        $setting = $this->find($key);
        $value = $this->settings->update($request->user(), $setting, $request->settingValue($setting));

        return new PlatformSettingResource($value);
    }

    private function find(string $key): PlatformSetting
    {
        $setting = PlatformSetting::tryFrom($key);

        if ($setting === null || ! in_array($setting, $this->settings->settings(), true)) {
            throw ApiException::notFound('SETTING_NOT_FOUND');
        }

        return $setting;
    }
}

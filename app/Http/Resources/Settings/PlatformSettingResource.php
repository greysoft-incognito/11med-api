<?php

namespace App\Http\Resources\Settings;

use App\Http\Resources\ApiResource;
use App\Services\Settings\SettingValue;
use Illuminate\Http\Request;

/**
 * Portal `zPlatformSetting`.
 *
 * @property SettingValue $resource
 */
class PlatformSettingResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $setting = $this->resource->setting;
        $bounds = $setting->bounds();

        return [
            'key' => $setting->value,
            'group' => $setting->group(),
            'label' => $setting->label(),
            'type' => $setting->type(),
            'value' => $this->resource->value,
            'default' => $this->resource->default,
            'min' => $bounds[0] ?? null,
            'max' => $bounds[1] ?? null,
            'overridden' => $this->resource->overridden,
            'updated_at' => $this->resource->overridden ? $this->iso($this->resource->updatedAt) : null,
        ];
    }
}

<?php

namespace App\Http\Requests\Settings;

use App\Enums\PlatformSetting;
use App\Http\Requests\ApiRequest;

/**
 * `zUpdateSettingRequest` (portal `src/api/contracts/operations.ts`): a value within the setting's
 * bounds, or null for the config file's default.
 */
class UpdateSettingRequest extends ApiRequest
{
    public function rules(): array
    {
        $setting = PlatformSetting::tryFrom((string) $this->route('key'));
        $bounds = $setting?->bounds();

        $type = match ($setting?->type()) {
            'boolean' => ['boolean'],
            'number' => ['numeric'],
            default => ['integer'],
        };

        return [
            'value' => [
                'present',
                'nullable',
                ...$type,
                ...($bounds === null ? [] : ["between:{$bounds[0]},{$bounds[1]}"]),
            ],
        ];
    }

    /**
     * The value as the setting holds it.
     */
    public function settingValue(PlatformSetting $setting): int|float|bool|null
    {
        $value = $this->validated('value');

        return match (true) {
            $value === null => null,
            $setting->type() === 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            $setting->type() === 'number' => (float) $value,
            default => (int) $value,
        };
    }
}

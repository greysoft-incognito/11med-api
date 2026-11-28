<?php

namespace App\Services\Settings;

use App\Enums\PlatformSetting;
use Carbon\CarbonInterface;

/**
 * A setting as it stands: its current value, the config file's default, and whether the team set it.
 */
final readonly class SettingValue
{
    public function __construct(
        public PlatformSetting $setting,
        public int|float|bool $value,
        public int|float|bool $default,
        public bool $overridden,
        public ?CarbonInterface $updatedAt,
    ) {}
}

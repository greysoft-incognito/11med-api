<?php

namespace App\Http\Resources\Settings;

use App\Http\Resources\ApiCollection;

/**
 * Every runtime setting, read whole: the list is fixed and small.
 */
class PlatformSettingCollection extends ApiCollection
{
    public $collects = PlatformSettingResource::class;
}

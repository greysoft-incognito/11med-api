<?php

namespace App\Http\Resources\Support;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zSupportMessage)`.
 */
class SupportMessageCollection extends ApiCollection
{
    public $collects = SupportMessageResource::class;
}

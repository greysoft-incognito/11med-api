<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * A laboratory's price list, read whole.
 */
class OfferedTestCollection extends ApiCollection
{
    public $collects = OfferedTestResource::class;
}

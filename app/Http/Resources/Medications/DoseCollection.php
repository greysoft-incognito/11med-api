<?php

namespace App\Http\Resources\Medications;

use App\Http\Resources\ApiCollection;

/**
 * A day's doses (an array), or dose history (paged, FE-003).
 */
class DoseCollection extends ApiCollection
{
    public $collects = DoseResource::class;
}

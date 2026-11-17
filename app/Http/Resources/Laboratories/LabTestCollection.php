<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * The catalogue, small and read whole.
 */
class LabTestCollection extends ApiCollection
{
    public $collects = LabTestResource::class;
}

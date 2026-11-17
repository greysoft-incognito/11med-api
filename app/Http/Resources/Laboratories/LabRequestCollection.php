<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * The patient's test requests, paged (FE-003).
 */
class LabRequestCollection extends ApiCollection
{
    public $collects = LabRequestResource::class;
}

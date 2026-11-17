<?php

namespace App\Http\Resources\Caregiving;

use App\Http\Resources\ApiCollection;

/**
 * `zCaringForList`, paged (FE-003).
 */
class CaredForPatientCollection extends ApiCollection
{
    public $collects = CaredForPatientResource::class;
}

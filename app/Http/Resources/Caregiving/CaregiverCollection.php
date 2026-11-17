<?php

namespace App\Http\Resources\Caregiving;

use App\Http\Resources\ApiCollection;

/**
 * `zCaregiverList`, paged (FE-003).
 */
class CaregiverCollection extends ApiCollection
{
    public $collects = CaregiverResource::class;
}

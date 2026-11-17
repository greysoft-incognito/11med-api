<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * The laboratory directory, paged (FE-003).
 */
class LaboratoryCollection extends ApiCollection
{
    public $collects = LaboratoryResource::class;
}

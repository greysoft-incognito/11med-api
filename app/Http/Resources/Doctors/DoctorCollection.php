<?php

namespace App\Http\Resources\Doctors;

use App\Http\Resources\ApiCollection;

/**
 * The doctor directory, paged (FE-003).
 */
class DoctorCollection extends ApiCollection
{
    public $collects = DoctorResource::class;
}

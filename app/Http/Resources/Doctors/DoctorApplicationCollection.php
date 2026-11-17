<?php

namespace App\Http\Resources\Doctors;

use App\Http\Resources\ApiCollection;

/**
 * The team's list of `zDoctorApplication`, paged.
 */
class DoctorApplicationCollection extends ApiCollection
{
    public $collects = DoctorApplicationResource::class;
}

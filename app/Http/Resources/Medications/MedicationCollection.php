<?php

namespace App\Http\Resources\Medications;

use App\Http\Resources\ApiCollection;

/**
 * A patient's schedules, oldest first, paged (FE-060).
 */
class MedicationCollection extends ApiCollection
{
    public $collects = MedicationResource::class;
}

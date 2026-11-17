<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * The patient's laboratory bookings, paged (FE-003).
 */
class LabBookingCollection extends ApiCollection
{
    public $collects = LabBookingResource::class;
}

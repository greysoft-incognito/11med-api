<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * The laboratory's bookings, paged.
 */
class LaboratoryBookingCollection extends ApiCollection
{
    public $collects = LaboratoryBookingResource::class;
}

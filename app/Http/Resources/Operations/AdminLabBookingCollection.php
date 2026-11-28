<?php

namespace App\Http\Resources\Operations;

use App\Http\Resources\ApiCollection;

/**
 * Every laboratory's bookings, for the team, paged.
 */
class AdminLabBookingCollection extends ApiCollection
{
    public $collects = AdminLabBookingResource::class;
}

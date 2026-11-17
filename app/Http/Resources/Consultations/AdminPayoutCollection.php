<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiCollection;

/**
 * The payout desk: pending first, then the newest periods, paged.
 */
class AdminPayoutCollection extends ApiCollection
{
    public $collects = AdminPayoutResource::class;
}

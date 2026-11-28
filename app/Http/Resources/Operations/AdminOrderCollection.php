<?php

namespace App\Http\Resources\Operations;

use App\Http\Resources\ApiCollection;

/**
 * Every pharmacy's orders, for the team, paged.
 */
class AdminOrderCollection extends ApiCollection
{
    public $collects = AdminOrderResource::class;
}

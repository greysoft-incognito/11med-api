<?php

namespace App\Http\Resources\Subscriptions;

use App\Http\Resources\ApiCollection;

/**
 * The admin desk's subscription list, paged.
 */
class SubscriptionRecordCollection extends ApiCollection
{
    public $collects = SubscriptionRecordResource::class;
}

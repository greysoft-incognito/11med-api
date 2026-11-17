<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiCollection;

/**
 * The patient's orders, paged (FE-003); checkout's answer, one per pharmacy, as a plain list.
 */
class OrderCollection extends ApiCollection
{
    public $collects = OrderResource::class;
}

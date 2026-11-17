<?php

namespace App\Http\Resources\Payments;

use App\Http\Resources\ApiCollection;

/**
 * The admin desk's payments, paged.
 */
class AdminPaymentCollection extends ApiCollection
{
    public $collects = AdminPaymentResource::class;
}

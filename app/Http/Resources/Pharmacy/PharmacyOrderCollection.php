<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zPharmacyOrder)`.
 */
class PharmacyOrderCollection extends ApiCollection
{
    public $collects = PharmacyOrderResource::class;
}

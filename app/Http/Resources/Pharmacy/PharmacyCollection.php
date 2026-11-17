<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiCollection;

/**
 * The app's pharmacy list, paged (FE-003).
 */
class PharmacyCollection extends ApiCollection
{
    public $collects = PharmacyResource::class;
}

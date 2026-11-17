<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zDrug)`.
 */
class DrugCollection extends ApiCollection
{
    public $collects = DrugResource::class;
}

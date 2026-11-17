<?php

namespace App\Http\Resources\Partners;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zPartner)`.
 */
class PartnerCollection extends ApiCollection
{
    public $collects = PartnerResource::class;
}

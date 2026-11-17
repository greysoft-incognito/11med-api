<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * The catalogue as one laboratory sees it, read whole.
 */
class CatalogueEntryCollection extends ApiCollection
{
    public $collects = CatalogueEntryResource::class;
}

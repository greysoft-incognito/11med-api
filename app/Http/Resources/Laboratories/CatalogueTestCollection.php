<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiCollection;

/**
 * The app's catalogue, read whole.
 */
class CatalogueTestCollection extends ApiCollection
{
    public $collects = CatalogueTestResource::class;
}

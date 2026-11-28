<?php

namespace App\Http\Resources\Documents;

use App\Http\Resources\ApiCollection;

/**
 * The built-in documents, small and read whole.
 */
class AdminDocumentCollection extends ApiCollection
{
    public $collects = AdminDocumentResource::class;
}

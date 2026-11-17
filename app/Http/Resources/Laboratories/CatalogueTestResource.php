<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\LabTest;
use Illuminate\Http\Request;

/**
 * App `zCatalogueTest`: a test with its cheapest current price and how many laboratories run it.
 *
 * @property LabTest $resource
 */
class CatalogueTestResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $test = $this->resource;

        return [
            ...LabTestResource::fields($test),
            'from_kobo' => $test->from_kobo === null ? null : (int) $test->from_kobo,
            'offered_by' => (int) $test->offered_by,
        ];
    }
}

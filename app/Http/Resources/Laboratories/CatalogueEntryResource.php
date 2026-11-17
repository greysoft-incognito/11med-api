<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\LabTest;
use Illuminate\Http\Request;

/**
 * Portal `zCatalogueEntry`: a catalogue test and this laboratory's price for it, if listed.
 *
 * @property LabTest $resource
 */
class CatalogueEntryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $test = $this->resource;

        return [
            ...LabTestResource::fields($test),
            'offered' => $test->offered_price_kobo === null ? null : [
                'price_kobo' => (int) $test->offered_price_kobo,
                'available' => (bool) $test->offered_available,
            ],
        ];
    }
}

<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\LaboratoryTest;
use Illuminate\Http\Request;

/**
 * `zOfferedTest`: the catalogue test as it stands, with this laboratory's price and availability.
 *
 * @property LaboratoryTest $resource
 */
class OfferedTestResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $offer = $this->resource;

        return [
            ...LabTestResource::fields($offer->test),
            'price_kobo' => $offer->price_kobo,
            'available' => $offer->available,
        ];
    }
}

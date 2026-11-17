<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\Laboratory;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zLaboratory`. The id is the organisation's; `test_price_kobo` is set when the directory was
 * filtered by a test.
 *
 * @property Laboratory $resource
 */
class LaboratoryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $laboratory = $this->resource;
        $organisation = $laboratory->organisation;
        $media = app(MediaLibrary::class);

        return [
            'id' => $organisation->uuid,
            'name' => $organisation->name,
            'state' => $organisation->state,
            'city' => $organisation->city,
            'address' => $organisation->address,
            'logo_url' => $laboratory->logo === null ? null : $media->url($laboratory->logo),
            'cover_url' => $laboratory->cover === null ? null : $media->url($laboratory->cover),
            'rating' => $laboratory->rating,
            'rating_count' => $laboratory->rating_count,
            'opens' => $laboratory->opens,
            'closes' => $laboratory->closes,
            'home_collection_fee_kobo' => $laboratory->home_collection_fee_kobo,
            'tests_count' => (int) $laboratory->tests_count,
            'test_price_kobo' => $laboratory->test_price_kobo === null ? null : (int) $laboratory->test_price_kobo,
        ];
    }
}

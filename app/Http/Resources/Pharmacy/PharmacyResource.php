<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiResource;
use App\Models\Pharmacy;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zPharmacy`. Its id is the organisation's; `in_stock_count` is counted with the query.
 *
 * @property Pharmacy $resource
 */
class PharmacyResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $pharmacy = $this->resource;
        $organisation = $pharmacy->organisation;
        $media = app(MediaLibrary::class);

        return [
            'id' => $organisation->uuid,
            'name' => $organisation->name,
            'state' => $organisation->state,
            'city' => $organisation->city,
            'address' => $organisation->address,
            'logo_url' => $pharmacy->logo === null ? null : $media->url($pharmacy->logo),
            'cover_url' => $pharmacy->cover === null ? null : $media->url($pharmacy->cover),
            'rating' => $pharmacy->rating,
            'rating_count' => $pharmacy->rating_count,
            'delivery_fee_kobo' => $pharmacy->delivery_fee_kobo,
            'free_delivery_over_kobo' => $pharmacy->free_delivery_over_kobo,
            'delivery_minutes' => $pharmacy->delivery_minutes,
            'in_stock_count' => (int) $pharmacy->in_stock_count,
        ];
    }
}

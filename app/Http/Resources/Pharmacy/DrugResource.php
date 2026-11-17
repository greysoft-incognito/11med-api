<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiResource;
use App\Models\Drug;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zDrug`, with the selling pharmacy's name and state.
 *
 * @property Drug $resource
 */
class DrugResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $drug = $this->resource;
        $organisation = $drug->organisation;

        return [
            'id' => $drug->uuid,
            'name' => $drug->name,
            'brand_name' => $drug->brand_name,
            'nafdac_number' => $drug->nafdac_number,
            'category' => $drug->category->value,
            'dosage_form' => $drug->dosage_form->value,
            'dosage_strength' => $drug->dosage_strength,
            'description' => $drug->description,
            'price_kobo' => $drug->price_kobo,
            'requires_prescription' => $drug->requires_prescription,
            'in_stock' => $drug->in_stock,
            'image_url' => $drug->image === null ? null : app(MediaLibrary::class)->url($drug->image),
            'pharmacy_id' => $organisation->uuid,
            'pharmacy_name' => $organisation->name,
            'pharmacy_state' => $organisation->state,
        ];
    }
}

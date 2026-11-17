<?php

namespace App\Http\Resources\Readings;

use App\Enums\ReadingType;
use App\Http\Resources\ApiResource;
use App\Models\Reading;
use Illuminate\Http\Request;

/**
 * `zReading`: the shape its `type` names.
 *
 * @property Reading $resource
 */
class ReadingResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $reading = $this->resource;

        $measure = match ($reading->type) {
            ReadingType::BloodPressure => [
                'systolic' => $reading->systolic,
                'diastolic' => $reading->diastolic,
                'pulse' => $reading->pulse,
            ],
            ReadingType::BloodGlucose => [
                'mmol_l' => $reading->mmol_l,
                'context' => $reading->context?->value,
            ],
            ReadingType::Weight => [
                'kg' => $reading->kg,
            ],
        };

        return [
            'id' => $reading->uuid,
            'type' => $reading->type->value,
            ...$measure,
            'taken_at' => $this->iso($reading->taken_at),
            'note' => $reading->note,
            'created_at' => $this->iso($reading->created_at),
        ];
    }
}

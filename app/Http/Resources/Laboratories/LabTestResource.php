<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\LabTest;
use Illuminate\Http\Request;

/**
 * `zLabTest`.
 *
 * @property LabTest $resource
 */
class LabTestResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(LabTest $test): array
    {
        return [
            'code' => $test->code,
            'name' => $test->name,
            'category' => $test->category->value,
            'sample' => $test->sample->value,
            'description' => $test->description,
            'fasting_hours' => $test->fasting_hours,
            'turnaround_hours' => $test->turnaround_hours,
        ];
    }
}

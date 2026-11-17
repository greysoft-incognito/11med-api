<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `zPharmacySummary`.
 *
 * @property array<string, int> $resource
 */
class PharmacySummaryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

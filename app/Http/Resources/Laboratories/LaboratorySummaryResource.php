<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * Portal `zLaboratorySummary`.
 *
 * @property array<string, int> $resource
 */
class LaboratorySummaryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

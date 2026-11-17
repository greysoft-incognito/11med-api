<?php

namespace App\Http\Resources\Readings;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `zInsights`, from `Insights::for()`.
 *
 * @property array{window_days: int, items: list<array<string, mixed>>} $resource
 */
class InsightsResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

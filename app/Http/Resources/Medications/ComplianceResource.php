<?php

namespace App\Http\Resources\Medications;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `zCompliance`, from `Compliance::for()`.
 *
 * @property array<string, mixed> $resource
 */
class ComplianceResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

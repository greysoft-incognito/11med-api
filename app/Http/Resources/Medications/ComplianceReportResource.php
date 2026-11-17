<?php

namespace App\Http\Resources\Medications;

use App\Http\Resources\ApiResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * `zComplianceReport`: a signed link to the PDF, lapsing in minutes.
 *
 * @property array{url: string, expires_at: CarbonImmutable, window_days: int, generated_at: CarbonImmutable} $resource
 */
class ComplianceReportResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource['url'],
            'expires_at' => $this->iso($this->resource['expires_at']),
            'window_days' => $this->resource['window_days'],
            'generated_at' => $this->iso($this->resource['generated_at']),
        ];
    }
}

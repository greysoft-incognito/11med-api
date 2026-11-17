<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\LabRequest;
use Illuminate\Http\Request;

/**
 * `zLabRequest`. `booking_id` is the booking that fulfils it, while one is not cancelled.
 *
 * @property LabRequest $resource
 */
class LabRequestResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $labRequest = $this->resource;

        return [
            'id' => $labRequest->uuid,
            'consultation_id' => $labRequest->consultation_id,
            'doctor_id' => $labRequest->doctor?->uuid,
            'doctor_name' => $labRequest->doctor_name,
            'mdcn_number' => $labRequest->mdcn_number,
            'tests' => array_map(fn(array $test) => [
                'test_code' => $test['test_code'],
                'test_name' => $test['test_name'],
                'reason' => $test['reason'] ?? null,
            ], $labRequest->tests),
            'notes' => $labRequest->notes,
            'issued_at' => $this->iso($labRequest->issued_at),
            'booking_id' => $labRequest->booking?->uuid,
        ];
    }
}

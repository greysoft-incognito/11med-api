<?php

namespace App\Http\Resources\Caregiving;

use App\Http\Resources\ApiResource;
use App\Models\Caregiver;
use Illuminate\Http\Request;

/**
 * `zCaredForPatient`: a patient whose schedule this account may read.
 *
 * @property Caregiver $resource
 */
class CaredForPatientResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'patient_id' => $this->resource->patient->uuid,
            'patient_name' => $this->resource->patient->full_name,
            'since' => $this->iso($this->resource->accepted_at),
        ];
    }
}

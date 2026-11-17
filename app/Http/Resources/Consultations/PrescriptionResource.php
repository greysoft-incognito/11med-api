<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiResource;
use App\Models\Prescription;
use Illuminate\Http\Request;

/**
 * `zPrescription`.
 *
 * @property Prescription $resource
 */
class PrescriptionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $prescription = $this->resource;

        return [
            'id' => $prescription->uuid,
            'consultation_id' => $prescription->consultation->uuid,
            'doctor_id' => $prescription->doctor?->uuid,
            'doctor_name' => $prescription->doctor_name,
            'mdcn_number' => $prescription->mdcn_number,
            'diagnosis_summary' => $prescription->diagnosis_summary,
            'drugs' => $prescription->drugs,
            'issued_at' => $this->iso($prescription->issued_at),
            'expires_at' => $this->iso($prescription->expires_at),
            'added_to_schedule' => $prescription->applied_at !== null,
        ];
    }
}

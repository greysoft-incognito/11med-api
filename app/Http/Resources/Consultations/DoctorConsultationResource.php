<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\Profile\MedicalProfileResource;
use App\Models\Consultation;
use Illuminate\Http\Request;

/**
 * A consultation as its doctor sees it (app `zDoctorConsultation`): the patient, their profile only
 * where they agreed to share it (§7.3), and the complaint a follow-up continues.
 *
 * @property Consultation $resource
 */
class DoctorConsultationResource extends ConsultationResource
{
    public function toArray(Request $request): array
    {
        $consultation = $this->resource;
        $patient = $consultation->patient;
        $profile = $consultation->profile_shared ? $patient?->medicalProfile : null;
        $shared = $profile === null ? null : (new MedicalProfileResource($profile))->toArray($request);

        return parent::toArray($request) + [
            'patient_id' => $patient?->uuid ?? '',
            'patient_name' => $patient?->full_name ?? '',
            'patient_profile' => $shared,
            'follow_up_complaint' => $consultation->followUpOf?->complaint,
        ];
    }
}

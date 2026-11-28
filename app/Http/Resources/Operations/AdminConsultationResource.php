<?php

namespace App\Http\Resources\Operations;

use App\Http\Resources\ApiResource;
use App\Models\Consultation;
use App\Services\Consultations\PaymentSummaries;
use Illuminate\Http\Request;

/**
 * Portal `zAdminConsultation`: who, when, what was paid and where it stands. Never the complaint,
 * the conversation or the prescription.
 *
 * @property Consultation $resource
 */
class AdminConsultationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $consultation = $this->resource;

        return [
            'id' => $consultation->uuid,
            'status' => $consultation->status->value,
            'session_type' => $consultation->session_type->value,
            'doctor_id' => $consultation->doctor?->uuid,
            'doctor_name' => $consultation->doctor_name,
            'doctor_specialty' => $consultation->doctor_specialty->value,
            'patient_id' => $consultation->patient?->uuid,
            'patient_name' => $consultation->patient?->full_name,
            'patient_phone' => $consultation->patient?->phone,
            'fee_kobo' => $consultation->fee_kobo,
            'payment' => app(PaymentSummaries::class)->of($consultation),
            'scheduled_for' => $this->iso($consultation->scheduled_for),
            'scheduled' => $consultation->scheduled,
            'expires_at' => $this->iso($consultation->expires_at),
            'started_at' => $this->iso($consultation->started_at),
            'ended_at' => $this->iso($consultation->ended_at),
            'created_at' => $this->iso($consultation->created_at),
        ];
    }
}

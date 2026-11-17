<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiResource;
use App\Models\Consultation;
use App\Services\Consultations\PaymentSummaries;
use Illuminate\Http\Request;

/**
 * `zConsultation`.
 *
 * @property Consultation $resource
 */
class ConsultationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $consultation = $this->resource;

        return [
            'id' => $consultation->uuid,
            'doctor_id' => $consultation->doctor?->uuid,
            'doctor_name' => $consultation->doctor_name,
            'doctor_specialty' => $consultation->doctor_specialty->value,
            'session_type' => $consultation->session_type->value,
            'status' => $consultation->status->value,
            'complaint' => $consultation->complaint,
            'fee_kobo' => $consultation->fee_kobo,
            'payment' => app(PaymentSummaries::class)->of($consultation),
            'scheduled_for' => $this->iso($consultation->scheduled_for),
            'scheduled' => $consultation->scheduled,
            'length_minutes' => $consultation->length_minutes,
            'follow_up_of' => $consultation->followUpOf?->uuid,
            'expires_at' => $this->iso($consultation->expires_at),
            'started_at' => $this->iso($consultation->started_at),
            'ended_at' => $this->iso($consultation->ended_at),
            'profile_shared' => $consultation->profile_shared,
            'rated' => $consultation->rated_at !== null,
            'created_at' => $this->iso($consultation->created_at),
        ];
    }
}

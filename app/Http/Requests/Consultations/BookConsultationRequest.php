<?php

namespace App\Http\Requests\Consultations;

use App\Enums\PaymentChannel;
use App\Enums\SessionType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zBookConsultationRequest`. The counter is a pickup's channel, not a consultation's.
 */
class BookConsultationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'uuid'],
            'session_type' => ['required', Rule::enum(SessionType::class)],
            'complaint' => ['required', 'string', 'min:10'],
            'scheduled_for' => ['present', 'nullable', 'date'],
            'profile_shared' => ['required', 'boolean'],
            'follow_up_of' => ['nullable', 'uuid'],
            'channel' => ['nullable', Rule::enum(PaymentChannel::class)->except([PaymentChannel::Counter])],
        ];
    }
}

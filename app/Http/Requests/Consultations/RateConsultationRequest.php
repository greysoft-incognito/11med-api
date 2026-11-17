<?php

namespace App\Http\Requests\Consultations;

use App\Http\Requests\ApiRequest;

/**
 * `zRateConsultationRequest`.
 */
class RateConsultationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'review' => ['nullable', 'string', 'max:500'],
        ];
    }
}

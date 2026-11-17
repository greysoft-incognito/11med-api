<?php

namespace App\Http\Requests\Medications;

use App\Http\Requests\ApiRequest;

/**
 * `zLogDosesBatchRequest`: the offline queue's answers.
 */
class LogDosesBatchRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1', 'max:' . config('elevenmed.medications.batch_max')],
            ...DoseRules::entry('entries.*.'),
        ];
    }
}

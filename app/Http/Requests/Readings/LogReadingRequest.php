<?php

namespace App\Http\Requests\Readings;

use App\Enums\GlucoseContext;
use App\Enums\ReadingType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zLogReadingRequest`: one of three shapes by `type`, each value within what a home device can
 * produce (`READING_LIMITS`).
 */
class LogReadingRequest extends ApiRequest
{
    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'type' => ['required', Rule::enum(ReadingType::class)],
            'taken_at' => ['sometimes', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/'],
            'note' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];

        return $rules + match ($type) {
            'blood_pressure' => [
                'systolic' => ['required', 'integer', 'between:60,260'],
                'diastolic' => ['required', 'integer', 'between:30,160'],
                'pulse' => ['present', 'nullable', 'integer', 'between:30,220'],
            ],
            'blood_glucose' => [
                'mmol_l' => ['required', 'numeric', 'between:1,35'],
                'context' => ['required', Rule::enum(GlucoseContext::class)],
            ],
            'weight' => [
                'kg' => ['required', 'numeric', 'between:20,300'],
            ],
            default => [],
        };
    }
}

<?php

namespace App\Http\Requests\Medications;

use App\Http\Requests\ApiRequest;

/**
 * The query of the dose reads: a Lagos `date` (FE-004), a `window` or `days` length, a
 * `medication_id`, and the paging keys. A length outside 1 to 365 is clamped, as the mock does.
 */
class WindowRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'window' => ['sometimes', 'integer'],
            'days' => ['sometimes', 'integer'],
            'medication_id' => ['sometimes', 'uuid'],
        ];
    }

    public function length(string $key, int $default, int $min = 1): int
    {
        $asked = $this->filled($key) ? (int) $this->input($key) : $default;

        return max($min, min($asked, config('elevenmed.medications.max_window_days')));
    }
}

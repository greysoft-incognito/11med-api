<?php

namespace App\Http\Requests\Readings;

use App\Enums\ReadingType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `GET /readings?type=&window=`: the window is clamped to 1 to 365 days, then to the account's.
 */
class ListReadingsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(ReadingType::class)],
            'window' => ['sometimes', 'integer'],
        ];
    }

    public function window(): int
    {
        $asked = $this->filled('window') ? (int) $this->input('window') : 7;

        return max(1, min($asked, config('elevenmed.medications.max_window_days')));
    }
}

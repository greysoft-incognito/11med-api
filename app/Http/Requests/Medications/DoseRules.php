<?php

namespace App\Http\Requests\Medications;

use App\Enums\DoseState;
use App\Enums\SkipReason;
use Illuminate\Validation\Rule;

/**
 * `zLogDoseRequest`, at the top level or inside a batch.
 */
final class DoseRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function entry(string $prefix = ''): array
    {
        return [
            "{$prefix}dose_id" => ['required', 'string', 'min:1', 'max:120'],
            "{$prefix}state" => ['required', Rule::enum(DoseState::class)],
            "{$prefix}skip_reason" => ['sometimes', 'nullable', Rule::enum(SkipReason::class)],
            "{$prefix}logged_at" => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/'],
        ];
    }
}

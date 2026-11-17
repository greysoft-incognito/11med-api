<?php

namespace App\Http\Requests\Medications;

use App\Enums\DosageForm;
use App\Enums\DoseUnit;
use App\Enums\FrequencyType;
use App\Enums\WithFood;
use Illuminate\Validation\Rule;

/**
 * The rules of `zCreateMedicationRequest` (app `src/api/contracts/medications.ts`). On an update
 * every key is optional, and a present required field may not be null.
 */
final class MedicationRules
{
    public const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /**
     * @return array<string, list<mixed>>
     */
    public static function for(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'drug_name' => [...$required, 'string', 'min:1', 'max:160'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'dosage_strength' => [...$required, 'string', 'min:1', 'max:60'],
            'dosage_form' => [...$required, Rule::enum(DosageForm::class)],
            'dose_quantity' => [...$required, 'numeric', 'gt:0', 'max:1000'],
            'dose_unit' => ['sometimes', 'nullable', Rule::enum(DoseUnit::class)],
            'frequency_type' => [...$required, Rule::enum(FrequencyType::class)],
            'specific_times' => [...$required, 'array', 'min:1', 'max:24'],
            'specific_times.*' => ['required', 'string', 'regex:' . self::TIME],
            'start_date' => [...$required, 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', ...($partial ? [] : ['after_or_equal:start_date'])],
            'with_food' => ['sometimes', 'nullable', Rule::enum(WithFood::class)],
            'special_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            'condition_being_treated' => ['sometimes', 'nullable', 'string', 'max:160'],
            'reminder_active' => [...$required, 'boolean:strict'],
            'caregiver_alerts' => ['sometimes', 'nullable', 'boolean:strict'],
            'refill_reminder_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'estimated_supply_units' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}

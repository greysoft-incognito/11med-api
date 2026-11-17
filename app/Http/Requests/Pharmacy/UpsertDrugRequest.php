<?php

namespace App\Http\Requests\Pharmacy;

use App\Enums\DosageForm;
use App\Enums\DrugCategory;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zUpsertDrugRequest`: the whole medicine, for a new one and an edit alike.
 */
class UpsertDrugRequest extends ApiRequest
{
    public const NAFDAC_PATTERN = '/^(A4-|B4-|04-|A11-)?\d{4,6}(-\d)?$|^[A-Z0-9]{2}-\d{4,6}$/i';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'brand_name' => ['present', 'nullable', 'string', 'max:120'],
            'nafdac_number' => ['required', 'string', 'max:20', 'regex:' . self::NAFDAC_PATTERN],
            'category' => ['required', Rule::enum(DrugCategory::class)],
            'dosage_form' => ['required', Rule::enum(DosageForm::class)],
            'dosage_strength' => ['required', 'string', 'max:60'],
            'description' => ['present', 'nullable', 'string', 'max:600'],
            'price_kobo' => ['required', 'integer', 'min:0', 'max:100000000'],
            'requires_prescription' => ['required', 'boolean:strict'],
            'in_stock' => ['required', 'boolean:strict'],
            'image_url' => ['present', 'nullable', 'string', 'max:2048'],
        ];
    }
}

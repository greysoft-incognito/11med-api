<?php

namespace App\Http\Requests\Consultations;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zIssuePrescriptionRequest`, and `acknowledge_allergy_conflicts` once the doctor has seen the
 * conflicts a first attempt was refused with (FE-100).
 */
class IssuePrescriptionRequest extends ApiRequest
{
    /** The app's `DOSAGE_FORMS`. */
    public const DOSAGE_FORMS = ['tablet', 'capsule', 'syrup', 'injection', 'cream', 'inhaler', 'drops'];

    /** The app's `LIQUID_UNITS`. */
    public const DOSE_UNITS = ['ml', 'spoon_5ml'];

    public function rules(): array
    {
        return [
            'diagnosis_summary' => ['required', 'string', 'min:3'],
            'drugs' => ['required', 'array', 'min:1', 'list'],
            'drugs.*.drug_name' => ['required', 'string', 'min:1'],
            'drugs.*.dosage_strength' => ['required', 'string', 'min:1'],
            'drugs.*.dosage_form' => ['required', Rule::in(self::DOSAGE_FORMS)],
            'drugs.*.dose_quantity' => ['required', 'numeric', 'gt:0'],
            'drugs.*.dose_unit' => ['nullable', Rule::in(self::DOSE_UNITS)],
            'drugs.*.times' => ['required', 'array', 'min:1', 'list'],
            'drugs.*.times.*' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'drugs.*.duration_days' => ['required', 'integer', 'min:1'],
            'drugs.*.special_instructions' => ['present', 'nullable', 'string'],
            'acknowledge_allergy_conflicts' => ['sometimes', 'boolean'],
        ];
    }
}

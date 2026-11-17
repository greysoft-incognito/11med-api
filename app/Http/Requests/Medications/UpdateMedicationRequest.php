<?php

namespace App\Http\Requests\Medications;

use App\Http\Requests\ApiRequest;

/**
 * `zUpdateMedicationRequest`: every key optional.
 */
class UpdateMedicationRequest extends ApiRequest
{
    public function rules(): array
    {
        $rules = MedicationRules::for(partial: true);

        if ($this->has('start_date')) {
            $rules['end_date'][] = 'after_or_equal:start_date';
        }

        return $rules;
    }
}

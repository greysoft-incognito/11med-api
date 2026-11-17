<?php

namespace App\Http\Requests\Medications;

use App\Http\Requests\ApiRequest;

/**
 * `zCreateMedicationRequest`.
 */
class CreateMedicationRequest extends ApiRequest
{
    public function rules(): array
    {
        return MedicationRules::for(partial: false);
    }
}

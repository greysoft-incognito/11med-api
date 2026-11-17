<?php

namespace App\Http\Requests\Medications;

use App\Http\Requests\ApiRequest;

/**
 * `zLogDoseRequest`.
 */
class LogDoseRequest extends ApiRequest
{
    public function rules(): array
    {
        return DoseRules::entry();
    }
}

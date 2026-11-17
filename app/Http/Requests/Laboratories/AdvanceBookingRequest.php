<?php

namespace App\Http\Requests\Laboratories;

use App\Http\Requests\ApiRequest;

/**
 * Portal `zAdvanceBookingRequest`: the results document on the last step.
 */
class AdvanceBookingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'results_url' => ['sometimes', 'string', 'min:1', 'max:2048'],
            'results_name' => ['sometimes', 'string', 'min:1', 'max:150'],
        ];
    }
}

<?php

namespace App\Http\Requests\Pharmacy;

use App\Http\Requests\ApiRequest;
use App\Support\PhoneNumber;

/**
 * `zAdvanceOrderRequest` (portal): the rider and the estimate, when handing a delivery over.
 */
class AdvanceOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'rider' => ['sometimes', 'nullable', 'array'],
            'rider.name' => ['required_with:rider', 'string', 'min:2', 'max:80'],
            'rider.phone' => ['required_with:rider', 'string', 'regex:' . PhoneNumber::PATTERN],
            'rider.vehicle' => ['nullable', 'string', 'max:80'],
            'estimated_delivery_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}

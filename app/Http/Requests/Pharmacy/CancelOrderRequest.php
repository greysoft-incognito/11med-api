<?php

namespace App\Http\Requests\Pharmacy;

use App\Http\Requests\ApiRequest;

/**
 * `zCancelOrderRequest`: the reason the patient reads.
 */
class CancelOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}

<?php

namespace App\Http\Requests\Laboratories;

use App\Http\Requests\ApiRequest;

/**
 * Portal `zCancelBookingRequest`: why, in a few words the patient reads.
 */
class CancelLabBookingRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}

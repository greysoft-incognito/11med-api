<?php

namespace App\Http\Requests\Consultations;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Portal `zSettlePayoutRequest`: paid with the transfer's reference, or failed with what failed.
 */
class SettlePayoutRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['paid', 'failed'])],
            'reference' => ['exclude_unless:decision,paid', 'required', 'string', 'min:4', 'max:64'],
            'reason' => ['exclude_unless:decision,failed', 'required', 'string', 'min:4', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['reference', 'reason'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }
}

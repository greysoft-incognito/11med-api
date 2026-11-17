<?php

namespace App\Http\Requests\Doctors;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zReviewApplicationRequest`: approved, rejected or suspended with notes, or reinstated.
 */
class ReviewApplicationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'rejected', 'suspended', 'reinstated'])],
            'notes' => ['exclude_unless:decision,rejected,suspended', 'required', 'string', 'min:12', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('notes'))) {
            $this->merge(['notes' => trim($this->input('notes'))]);
        }
    }
}

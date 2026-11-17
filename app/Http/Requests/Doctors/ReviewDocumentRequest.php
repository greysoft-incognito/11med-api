<?php

namespace App\Http\Requests\Doctors;

use App\Enums\DocumentKind;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zReviewDocumentRequest`: accepted, or rejected with a reason the doctor can act on.
 */
class ReviewDocumentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(DocumentKind::class)],
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'reason' => ['exclude_unless:decision,rejected', 'required', 'string', 'min:12', 'max:300'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }
}

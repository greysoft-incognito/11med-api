<?php

namespace App\Http\Requests\Partners;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zReviewPartnerRequest`: a suspension carries the reason the partner is told.
 */
class ReviewPartnerRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'suspended', 'reinstated'])],
            'notes' => ['required_if:decision,suspended', 'nullable', 'string', 'min:12', 'max:500'],
        ];
    }
}

<?php

namespace App\Http\Requests\Laboratories;

use App\Enums\LabCollection;
use App\Enums\PaymentChannel;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * App `zBookLabRequest`. The address is checked against the collection by the booking itself.
 */
class BookLabRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'laboratory_id' => ['required', 'uuid'],
            'test_codes' => ['required', 'array', 'min:1', 'max:30'],
            'test_codes.*' => ['required', 'string', 'min:1', 'max:16'],
            'collection' => ['required', Rule::enum(LabCollection::class)],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.line1' => ['required_with:address', 'string', 'min:1', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required_with:address', 'string', 'min:1', 'max:80'],
            'address.state' => ['required_with:address', 'string', 'min:1', 'max:80'],
            'address.directions' => ['nullable', 'string', 'max:255'],
            'scheduled_for' => ['required', 'date'],
            'request_id' => ['sometimes', 'nullable', 'uuid'],
            'channel' => ['sometimes', Rule::enum(PaymentChannel::class)],
        ];
    }
}

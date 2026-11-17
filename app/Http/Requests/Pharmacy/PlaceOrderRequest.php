<?php

namespace App\Http\Requests\Pharmacy;

use App\Enums\Fulfilment;
use App\Enums\PaymentChannel;
use App\Http\Requests\ApiRequest;
use App\Support\NigerianGeography;
use Illuminate\Validation\Rule;

/**
 * `zPlaceOrderRequest`: an address for a delivery, ignored for a pickup.
 */
class PlaceOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        $delivery = 'required_if:fulfilment,' . Fulfilment::Delivery->value;

        return [
            'fulfilment' => ['required', Rule::enum(Fulfilment::class)],
            'address' => [$delivery, 'nullable', 'array'],
            'address.line1' => [$delivery, 'string', 'max:200'],
            'address.line2' => ['nullable', 'string', 'max:200'],
            'address.city' => [$delivery, 'string', 'max:80'],
            'address.state' => [$delivery, 'string', Rule::in(NigerianGeography::states())],
            'address.directions' => ['nullable', 'string', 'max:300'],
            'channel' => ['sometimes', 'nullable', Rule::enum(PaymentChannel::class)],
        ];
    }
}

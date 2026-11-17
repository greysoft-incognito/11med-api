<?php

namespace App\Http\Requests\Pharmacy;

use App\Http\Requests\ApiRequest;
use App\Services\Pharmacy\Cart;

/**
 * `zUpdateCartRequest`: zero removes the line.
 */
class UpdateCartRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'drug_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:0', 'max:' . Cart::MAX_QUANTITY],
        ];
    }
}

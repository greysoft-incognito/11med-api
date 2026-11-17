<?php

namespace App\Http\Requests\Pharmacy;

use App\Http\Requests\ApiRequest;
use App\Support\NigerianGeography;
use Illuminate\Validation\Rule;

/**
 * `zUpdatePharmacyRequest` (portal `src/api/contracts/pharmacy.ts`).
 */
class UpdatePharmacyRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', Rule::in(NigerianGeography::states())],
            'city' => ['present', 'nullable', 'string', 'max:80'],
            'address' => ['present', 'nullable', 'string', 'max:200'],
            'logo_url' => ['present', 'nullable', 'string', 'max:2048'],
            'cover_url' => ['present', 'nullable', 'string', 'max:2048'],
            'delivery_fee_kobo' => ['required', 'integer', 'min:0', 'max:100000000'],
            'free_delivery_over_kobo' => ['present', 'nullable', 'integer', 'min:0', 'max:10000000000'],
            'delivery_minutes' => ['present', 'nullable', 'integer', 'min:1', 'max:10080'],
        ];
    }
}

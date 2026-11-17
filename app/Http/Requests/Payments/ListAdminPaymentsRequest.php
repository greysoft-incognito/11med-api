<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentStatus;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * The admin desk's filters: a `status` and a search `q` over reference, target, patient and partner.
 */
class ListAdminPaymentsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}

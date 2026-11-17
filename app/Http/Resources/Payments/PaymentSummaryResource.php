<?php

namespace App\Http\Resources\Payments;

use App\Http\Resources\ApiResource;
use App\Services\Payments\PaymentSummary;
use Illuminate\Http\Request;

/**
 * `zPaymentSummary`, for the order, booking or consultation resources that embed it.
 *
 * @property PaymentSummary $resource
 */
class PaymentSummaryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}

<?php

namespace App\Http\Resources\Payments;

use App\Http\Resources\ApiResource;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * `zPayment`: the payment as a whole, for the patient who started it.
 *
 * @property Payment $resource
 */
class PaymentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->resource;

        return [
            'reference' => $payment->reference,
            'status' => $payment->status->value,
            'channel' => $payment->channel?->value,
            'amount_kobo' => $payment->amount_kobo,
            'authorization_url' => $payment->isPending() ? $payment->authorization_url : null,
            'paid_at' => $this->iso($payment->paid_at),
            'purpose' => $payment->purpose->value,
            'target_ids' => $payment->targetIds(),
            'created_at' => $this->iso($payment->created_at),
            'failure_reason' => $payment->failure_reason,
        ];
    }
}

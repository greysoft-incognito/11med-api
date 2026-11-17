<?php

namespace App\Http\Resources\Payments;

use App\Http\Resources\ApiResource;
use App\Models\PaymentTarget;
use Illuminate\Http\Request;

/**
 * Portal `zAdminPayment`: one row per target, with the patient read in the desk's query.
 *
 * @property PaymentTarget $resource
 */
class AdminPaymentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $share = $this->resource;
        $payment = $share->payment;

        return [
            'reference' => $payment->reference,
            'purpose' => $share->purpose->value,
            'target_id' => $share->target_id,
            'target_reference' => $share->target_reference,
            'partner_name' => $share->partner_name ?? '',
            'patient_name' => $share->getAttribute('patient_name'),
            'patient_phone' => $share->getAttribute('patient_phone'),
            'amount_kobo' => $share->amount_kobo,
            'channel' => $payment->channel?->value,
            'status' => $share->state->shownAs($payment->status)->value,
            'paid_at' => $this->iso($payment->paid_at),
            'created_at' => $this->iso($payment->created_at),
            'failure_reason' => $payment->failure_reason,
        ];
    }
}

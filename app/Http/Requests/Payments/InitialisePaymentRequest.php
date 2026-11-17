<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zInitializePaymentRequest`.
 */
class InitialisePaymentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(PaymentPurpose::class)],
            'target_id' => ['required', 'uuid'],
            'channel' => ['nullable', Rule::enum(PaymentChannel::class)],
        ];
    }

    public function purpose(): PaymentPurpose
    {
        return PaymentPurpose::from($this->validated('purpose'));
    }

    public function channel(): ?PaymentChannel
    {
        return PaymentChannel::tryFrom((string) $this->validated('channel'));
    }
}

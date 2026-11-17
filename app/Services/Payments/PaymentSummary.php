<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\PaymentTarget;
use Carbon\CarbonInterface;

/**
 * What a target carries about its payment (app `zPaymentSummary`), as its own share stands.
 */
final readonly class PaymentSummary
{
    public function __construct(
        public string $reference,
        public PaymentStatus $status,
        public ?PaymentChannel $channel,
        public int $amountKobo,
        public ?string $authorizationUrl,
        public ?CarbonInterface $paidAt,
    ) {}

    public static function ofShare(PaymentTarget $share): self
    {
        $payment = $share->payment;
        $status = $share->state->shownAs($payment->status);

        return new self(
            $payment->reference,
            $status,
            $payment->channel,
            $payment->amount_kobo,
            $status === PaymentStatus::Pending ? $payment->authorization_url : null,
            $payment->paid_at,
        );
    }

    /**
     * @return array{reference: string, status: string, channel: ?string, amount_kobo: int, authorization_url: ?string, paid_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'channel' => $this->channel?->value,
            'amount_kobo' => $this->amountKobo,
            'authorization_url' => $this->authorizationUrl,
            'paid_at' => $this->paidAt
                ?->copy()
                ->utc()
                ->toIso8601ZuluString('millisecond'),
        ];
    }
}

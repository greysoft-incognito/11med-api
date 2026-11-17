<?php

namespace Tests\Feature\Consultations;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Models\User;
use App\Services\Payments\Payable;
use App\Services\Payments\Payments;

/**
 * Records what the Consultations module asks of payments, so these tests do not depend on the gateway.
 */
class FakePayments implements Payments
{
    /** @var list<string> */
    public array $initialised = [];

    /** @var list<string> */
    public array $refunded = [];

    public ?PaymentChannel $lastChannel = null;

    public function initialise(array $targets, User $payer, ?PaymentChannel $channel = null): void
    {
        foreach ($targets as $target) {
            $this->initialised[] = $target->paymentTargetId();
        }

        $this->lastChannel = $channel;
    }

    public function summaryFor(Payable $target): ?array
    {
        if (! in_array($target->paymentTargetId(), $this->initialised, true)) {
            return null;
        }

        return [
            'reference' => '11M-' . substr($target->paymentTargetId(), -8),
            'status' => in_array($target->paymentTargetId(), $this->refunded, true) ? 'refunded' : 'pending',
            'channel' => $this->lastChannel?->value,
            'amount_kobo' => $target->amountKobo(),
            'authorization_url' => 'https://checkout.paystack.com/test',
            'paid_at' => null,
        ];
    }

    public function summariesFor(PaymentPurpose $purpose, array $targetIds): array
    {
        return [];
    }

    public function openAtCounter(Payable $target): void {}

    public function collectAtCounter(Payable $target): void {}

    public function refund(Payable $target, ?int $amountKobo = null): void
    {
        $this->refunded[] = $target->paymentTargetId();
    }
}

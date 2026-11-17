<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentShareState;
use App\Events\Payments\PaymentAbandoned;
use App\Events\Payments\PaymentFailed;
use App\Events\Payments\PaymentRefunded;
use App\Events\Payments\PaymentSucceeded;
use App\Models\Payment;
use App\Models\PaymentTarget;

/**
 * Announces what happened to a payment once per target it covers, with the target's own `Payable`
 * from its module. A target its module can no longer find, or an erased payer's, is skipped.
 */
class PaymentEvents
{
    public function __construct(
        private readonly PayableResolver $resolver,
    ) {}

    public function succeeded(Payment $payment): void
    {
        $channel = $payment->channel ?? PaymentChannel::Card;

        $this->each($payment, fn(Payable $target) => PaymentSucceeded::dispatch($target, $payment->reference, $channel));
    }

    public function failed(Payment $payment): void
    {
        $this->each($payment, fn(Payable $target) => PaymentFailed::dispatch($target, $payment->reference));
    }

    public function abandoned(Payment $payment): void
    {
        $this->each($payment, fn(Payable $target) => PaymentAbandoned::dispatch($target, $payment->reference));
    }

    public function refunded(Payment $payment, PaymentTarget $share, int $amountKobo): void
    {
        $target = $this->resolver->find($share->purpose, $share->target_id, $payment->user);

        if ($target !== null) {
            PaymentRefunded::dispatch($target, $payment->reference, $amountKobo);
        }
    }

    private function each(Payment $payment, callable $announce): void
    {
        $payment
            ->targets()
            ->where('state', '<>', PaymentShareState::Released->value)
            ->get()
            ->each(function (PaymentTarget $share) use ($payment, $announce) {
                $target = $this->resolver->find($share->purpose, $share->target_id, $payment->user);

                if ($target !== null) {
                    $announce($target);
                }
            });
    }
}

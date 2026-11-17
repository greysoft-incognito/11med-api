<?php

namespace Tests\Fakes;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Events\Payments\PaymentSucceeded;
use App\Models\User;
use App\Services\Payments\Payable;
use App\Services\Payments\Payments;

/**
 * Payments in memory, for tests of the modules that are paid for: a pending summary on
 * initialise, `pay()` to stand in for the webhook, refunds recorded.
 */
class FakePayments implements Payments
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $summaries = [];

    /**
     * @var list<array{target: string, amount: int|null}>
     */
    public array $refunds = [];

    public function initialise(array $targets, User $payer, ?PaymentChannel $channel = null): void
    {
        $reference = 'PAY-' . strtoupper(substr(md5(uniqid()), 0, 10));
        $amount = array_sum(array_map(fn(Payable $target) => $target->amountKobo(), $targets));

        foreach ($targets as $target) {
            $this->summaries[$target->paymentTargetId()] = [
                'reference' => $reference,
                'status' => 'pending',
                'channel' => $channel?->value,
                'amount_kobo' => $amount,
                'authorization_url' => "elevenmed://pay?reference={$reference}",
                'paid_at' => null,
            ];
        }
    }

    public function summaryFor(Payable $target): ?array
    {
        return $this->summaries[$target->paymentTargetId()] ?? null;
    }

    public function summariesFor(PaymentPurpose $purpose, array $targetIds): array
    {
        return array_intersect_key($this->summaries, array_flip($targetIds));
    }

    public function openAtCounter(Payable $target): void {}

    public function collectAtCounter(Payable $target): void {}

    public function refund(Payable $target, ?int $amountKobo = null): void
    {
        $this->refunds[] = ['target' => $target->paymentTargetId(), 'amount' => $amountKobo];

        if (isset($this->summaries[$target->paymentTargetId()])) {
            $paid = $this->summaries[$target->paymentTargetId()]['status'] === 'paid';
            $this->summaries[$target->paymentTargetId()]['status'] = $paid ? 'refunded' : 'abandoned';
            $this->summaries[$target->paymentTargetId()]['authorization_url'] = null;
        }
    }

    /**
     * What the webhook does on `charge.success`.
     */
    public function pay(Payable $target, PaymentChannel $channel = PaymentChannel::Card): void
    {
        $summary = &$this->summaries[$target->paymentTargetId()];
        $summary['status'] = 'paid';
        $summary['channel'] = $channel->value;
        $summary['authorization_url'] = null;
        $summary['paid_at'] = now()->utc()->toIso8601ZuluString('millisecond');

        PaymentSucceeded::dispatch($target, $summary['reference'], $channel);
    }
}

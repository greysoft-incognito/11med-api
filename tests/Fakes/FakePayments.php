<?php

namespace Tests\Fakes;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Events\Payments\PaymentRefunded;
use App\Events\Payments\PaymentSucceeded;
use App\Models\User;
use App\Services\Payments\Payable;
use App\Services\Payments\Payments;

/**
 * A gateway that remembers what it was asked and settles on command, for feature tests.
 */
class FakePayments implements Payments
{
    /** @var array<string, array<string, mixed>> */
    public array $summaries = [];

    /** @var list<array{target: string, amount: int|null}> */
    public array $refunds = [];

    private int $sequence = 0;

    public function initialise(array $targets, User $payer, ?PaymentChannel $channel = null): void
    {
        $reference = 'PAY-' . ++$this->sequence;

        foreach ($targets as $target) {
            $this->summaries[$target->paymentTargetId()] = [
                'reference' => $reference,
                'status' => PaymentStatus::Pending->value,
                'channel' => $channel?->value,
                'amount_kobo' => $target->amountKobo(),
                'authorization_url' => 'https://checkout.paystack.com/' . $reference,
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

    public function openAtCounter(Payable $target): void
    {
        $this->summaries[$target->paymentTargetId()] = [
            'reference' => 'PAY-' . ++$this->sequence,
            'status' => PaymentStatus::Pending->value,
            'channel' => PaymentChannel::Counter->value,
            'amount_kobo' => $target->amountKobo(),
            'authorization_url' => null,
            'paid_at' => null,
        ];
    }

    public function collectAtCounter(Payable $target): void
    {
        $this->settle($target, PaymentChannel::Counter);
    }

    public function refund(Payable $target, ?int $amountKobo = null): void
    {
        $this->refunds[] = ['target' => $target->paymentTargetId(), 'amount' => $amountKobo];

        $summary = $this->summaries[$target->paymentTargetId()] ?? null;

        if ($summary !== null && $summary['status'] === PaymentStatus::Paid->value) {
            $this->summaries[$target->paymentTargetId()]['status'] = PaymentStatus::Refunded->value;

            PaymentRefunded::dispatch($target, $summary['reference'], $amountKobo ?? $target->amountKobo());
        }
    }

    /**
     * What the webhook does on `charge.success`.
     */
    public function settle(Payable $target, PaymentChannel $channel = PaymentChannel::Card): void
    {
        $summary = $this->summaries[$target->paymentTargetId()];

        $this->summaries[$target->paymentTargetId()] = [
            ...$summary,
            'status' => PaymentStatus::Paid->value,
            'channel' => $channel->value,
            'authorization_url' => null,
            'paid_at' => now()->toIso8601ZuluString('millisecond'),
        ];

        PaymentSucceeded::dispatch($target, $summary['reference'], $channel);
    }
}

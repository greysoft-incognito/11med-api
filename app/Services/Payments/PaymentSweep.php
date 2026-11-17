<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\Payments\RequestRefund;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Payments\Gateway\GatewayUnavailable;
use App\Services\Payments\Gateway\GatewayVerdict;
use App\Services\Payments\Gateway\PaymentGateway;
use Illuminate\Database\Eloquent\Builder;

/**
 * The scheduled pass: a gateway payment pending past the timeout is asked about once more and
 * settled, or abandoned; refunds the gateway was never reached for are asked again.
 */
class PaymentSweep
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Settlement $settlement,
    ) {}

    /**
     * @return array{abandoned: int, settled: int, refunds: int}
     */
    public function run(): array
    {
        $counts = ['abandoned' => 0, 'settled' => 0, 'refunds' => 0];
        $cutoff = now()->subMinutes(config('elevenmed.payments.abandon_after_minutes'));

        Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '<', $cutoff)
            ->where(fn(Builder $q) => $q
                ->whereNull('channel')
                ->orWhere('channel', '<>', PaymentChannel::Counter->value))
            ->lazyById(200)
            ->each(function (Payment $payment) use (&$counts) {
                $outcome = $this->close($payment);

                if ($outcome !== null) {
                    $counts[$outcome]++;
                }
            });

        PaymentRefund::query()
            ->where('status', RefundStatus::Pending->value)
            ->whereNull('requested_at')
            ->where('created_at', '<', now()->subMinutes(15))
            ->lazyById(200)
            ->each(function (PaymentRefund $refund) use (&$counts) {
                RequestRefund::dispatch($refund);
                $counts['refunds']++;
            });

        return $counts;
    }

    private function close(Payment $payment): ?string
    {
        $verdict = null;

        if ($this->gateway->configured()) {
            try {
                $verdict = $this->gateway->verify($payment->reference);
            } catch (GatewayUnavailable $e) {
                report($e);

                return null;
            }
        }

        if ($verdict !== null && $verdict->outcome !== null && $verdict->outcome !== GatewayVerdict::ABANDONED) {
            $this->settlement->apply($payment, $verdict);

            return 'settled';
        }

        $this->settlement->abandoned($payment);

        return 'abandoned';
    }
}

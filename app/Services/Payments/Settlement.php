<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\Gateway\GatewayUnavailable;
use App\Services\Payments\Gateway\GatewayVerdict;
use App\Services\Payments\Gateway\PaymentGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settles pending payments. Every outcome arrives as the gateway's verdict, from the webhook, a
 * verify call or the abandonment sweep, and is applied to the locked row once. Refunds are `Refunds`'.
 */
class Settlement
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Refunds $refunds,
        private readonly PaymentShares $shares,
        private readonly PaymentEvents $events,
    ) {}

    public function apply(Payment $payment, GatewayVerdict $verdict): Payment
    {
        return match ($verdict->outcome) {
            GatewayVerdict::PAID => $this->paid($payment, $verdict),
            GatewayVerdict::FAILED => $this->failed($payment, $verdict->gatewayResponse),
            GatewayVerdict::ABANDONED => $this->abandoned($payment),
            default => $payment,
        };
    }

    /**
     * Asks the gateway about a pending payment if it has not been asked recently.
     */
    public function refresh(Payment $payment): Payment
    {
        $every = config('elevenmed.payments.check_every_seconds');

        if (! $payment->isPending() || $payment->atCounter() || ! $this->gateway->configured()) {
            return $payment;
        }

        if ($payment->checked_at !== null && $payment->checked_at->diffInSeconds(now()) < $every) {
            return $payment;
        }

        $payment->forceFill(['checked_at' => now()])->save();

        try {
            $verdict = $this->gateway->verify($payment->reference);
        } catch (GatewayUnavailable $e) {
            report($e);

            return $payment;
        }

        return $verdict->outcome === GatewayVerdict::ABANDONED
            ? $payment
            : $this->apply($payment, $verdict);
    }

    /**
     * A charge cleared. If its targets were meanwhile paid by another payment the money is returned
     * in full; otherwise any other pending payment for them is abandoned.
     */
    public function paid(Payment $payment, GatewayVerdict $verdict): Payment
    {
        return DB::transaction(function () use ($payment, $verdict) {
            $payment = $this->lock($payment);

            if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
                return $payment;
            }

            if ($verdict->amountKobo < $payment->amount_kobo) {
                Log::warning('Payment amount short of the charge.', ['reference' => $payment->reference]);

                return $payment;
            }

            $duplicate = $this->paidElsewhere($payment);

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'channel' => $verdict->channel ?? $payment->channel ?? PaymentChannel::Card,
                'paid_at' => $verdict->paidAt ?? now(),
                'authorization_url' => null,
                'failure_reason' => null,
            ])->save();

            $payment->load('targets');

            if ($duplicate) {
                $this->refunds->refundWhole($payment);

                return $payment;
            }

            $this->abandonPendingFor($payment->purpose, $payment->targetIds(), $payment->id);

            $this->events->succeeded($payment);

            return $payment;
        });
    }

    public function paidAtCounter(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = $this->lock($payment);

            if (! $payment->isPending()) {
                return $payment;
            }

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ])->save();

            $payment->load('targets');

            $this->events->succeeded($payment);

            return $payment;
        });
    }

    public function failed(Payment $payment, ?string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment = $this->lock($payment);

            if (! $payment->isPending()) {
                return $payment;
            }

            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'authorization_url' => null,
                'failure_reason' => $reason ?? __('errors.PAYMENT_DECLINED'),
            ])->save();

            $payment->load('targets');

            $this->events->failed($payment);

            return $payment;
        });
    }

    public function abandoned(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = $this->lock($payment);

            if (! $payment->isPending()) {
                return $payment;
            }

            $payment->forceFill([
                'status' => PaymentStatus::Abandoned,
                'authorization_url' => null,
            ])->save();

            $payment->load('targets');

            $this->events->abandoned($payment);

            return $payment;
        });
    }

    /**
     * Abandons every pending payment covering any of these targets, but the one named.
     *
     * @param  list<string>  $targetIds
     */
    public function abandonPendingFor(PaymentPurpose $purpose, array $targetIds, ?int $except = null): void
    {
        Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->when($except !== null, fn(Builder $q) => $q->whereKeyNot($except))
            ->whereHas('targets', fn(Builder $q) => $q
                ->where('purpose', $purpose->value)
                ->whereIn('target_id', $targetIds))
            ->lockForUpdate()
            ->get()
            ->each(fn(Payment $pending) => $this->abandoned($pending));
    }

    /**
     * Whether another payment already stands paid for any target this one covers.
     */
    private function paidElsewhere(Payment $payment): bool
    {
        $payment->loadMissing('targets');

        return $this->shares
            ->paid($payment->purpose, $payment->targetIds(), $payment->id)
            ->isNotEmpty();
    }

    private function lock(Payment $payment): Payment
    {
        return Payment::query()
            ->whereKey($payment->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}

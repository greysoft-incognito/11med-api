<?php

namespace App\Services\Payments;

use App\Enums\PaymentShareState;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\Payments\PaymentAbandoned;
use App\Events\Payments\PaymentRefunded;
use App\Exceptions\ApiException;
use App\Jobs\Payments\RequestRefund;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTarget;
use App\Services\Payments\Gateway\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Returning money for a cancelled target, by its share (app handover §16, "Refunds"). A share shows
 * refunded once the gateway confirms; one not yet paid for is released instead.
 */
class Refunds
{
    public function __construct(
        private readonly PaymentShares $shares,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Refunds a target's share, all of it unless an amount is named. Idempotent while a refund is open.
     */
    public function refund(Payable $target, ?int $kobo = null): void
    {
        $current = $this->shares->current($target->paymentPurpose(), $target->paymentTargetId());

        if ($current === null) {
            return;
        }

        DB::transaction(function () use ($current, $kobo) {
            $payment = $this->lockPayment($current->payment_id);
            $share = $this->lockShare($current->id);

            match ($payment->status) {
                PaymentStatus::Pending => $this->release($payment, $share),
                PaymentStatus::Paid => $this->open($payment, $share, $kobo),
                default => null,
            };
        });
    }

    /**
     * Returns everything a payment took, for a charge that duplicated another. Runs inside the
     * settling transaction.
     */
    public function refundWhole(Payment $payment): void
    {
        $payment
            ->targets()
            ->lockForUpdate()
            ->get()
            ->each(fn(PaymentTarget $share) => $this->open($payment, $share, null));
    }

    /**
     * Asks the gateway for a refund recorded as pending. The queued job calls this.
     */
    public function request(PaymentRefund $refund): void
    {
        if ($refund->status !== RefundStatus::Pending || $refund->gateway_refund_id !== null) {
            return;
        }

        $id = $this->gateway->refund($refund->payment->reference, $refund->amount_kobo);

        $refund->forceFill([
            'gateway_refund_id' => $id !== '' ? $id : null,
            'requested_at' => now(),
        ])->save();
    }

    /**
     * The gateway returned the money.
     */
    public function confirm(PaymentRefund $refund): void
    {
        DB::transaction(function () use ($refund) {
            $payment = $this->lockPayment($refund->payment_id);
            $refund = PaymentRefund::query()
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->status === RefundStatus::Processed) {
                return;
            }

            $share = $this->lockShare($refund->payment_target_id);

            $refund->forceFill([
                'status' => RefundStatus::Processed,
                'processed_at' => now(),
                'failure_reason' => null,
            ])->save();

            $share->refunded_kobo += $refund->amount_kobo;
            $share->state = $share->refundableKobo() <= 0 ? PaymentShareState::Refunded : PaymentShareState::Standing;
            $share->save();

            $this->closeIfSettled($payment);

            PaymentRefunded::dispatch($payment->load('targets'), $share->purpose, $share->target_id, $refund->amount_kobo);
        });
    }

    /**
     * The gateway would not return the money. The share stays paid and the team is left to act.
     */
    public function fail(PaymentRefund $refund, ?string $reason): void
    {
        DB::transaction(function () use ($refund, $reason) {
            $refund = PaymentRefund::query()
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->status !== RefundStatus::Pending) {
                return;
            }

            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'failure_reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
            ])->save();

            $this->lockShare($refund->payment_target_id)
                ->forceFill(['state' => PaymentShareState::RefundFailed])
                ->save();

            Log::error('Paystack refused a refund.', ['refund' => $refund->uuid]);
        });
    }

    private function open(Payment $payment, PaymentTarget $share, ?int $kobo): void
    {
        if (! in_array($share->state, [PaymentShareState::Standing, PaymentShareState::RefundFailed], true)) {
            return;
        }

        $amount = $kobo ?? $share->refundableKobo();

        if ($amount < 1 || $amount > $share->refundableKobo()) {
            throw ApiException::unprocessable('REFUND_EXCEEDS_SHARE');
        }

        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'payment_target_id' => $share->id,
            'amount_kobo' => $amount,
            'status' => RefundStatus::Pending,
        ]);

        $share->forceFill(['state' => PaymentShareState::RefundPending])->save();

        if ($payment->atCounter()) {
            $this->confirm($refund);

            return;
        }

        RequestRefund::dispatch($refund)->afterCommit();
    }

    /**
     * A target cancelled before it was paid for leaves the payment; one with nothing left is abandoned.
     */
    private function release(Payment $payment, PaymentTarget $share): void
    {
        $share->forceFill(['state' => PaymentShareState::Released])->save();

        $standing = $payment
            ->targets()
            ->where('state', '<>', PaymentShareState::Released->value)
            ->exists();

        if ($standing) {
            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Abandoned,
            'authorization_url' => null,
        ])->save();

        PaymentAbandoned::dispatch($payment->load('targets'));
    }

    /**
     * A paid payment is refunded once nothing it covered still stands.
     */
    private function closeIfSettled(Payment $payment): void
    {
        $states = $payment
            ->targets()
            ->pluck('state')
            ->map(fn($state) => $state instanceof PaymentShareState ? $state : PaymentShareState::from($state));

        $closed = $states->every(fn(PaymentShareState $s) => in_array($s, [PaymentShareState::Refunded, PaymentShareState::Released], true));

        if ($closed && $states->contains(PaymentShareState::Refunded)) {
            $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
        }
    }

    private function lockPayment(int $id): Payment
    {
        return Payment::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockShare(int $id): PaymentTarget
    {
        return PaymentTarget::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}

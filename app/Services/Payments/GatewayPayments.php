<?php

namespace App\Services\Payments;

use App\Enums\HttpStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentShareState;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\PaymentTarget;
use App\Models\User;
use App\Services\Payments\Gateway\GatewayCheckout;
use App\Services\Payments\Gateway\GatewayUnavailable;
use App\Services\Payments\Gateway\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payments through Paystack (app handover §16): starting them and reading them. Settling them is
 * `Settlement`'s, refunding them `Refunds`'.
 */
class GatewayPayments implements Payments
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Settlement $settlement,
        private readonly Refunds $refunds,
        private readonly PaymentShares $shares,
    ) {}

    public function initialise(array $targets, User $payer, ?PaymentChannel $channel = null): void
    {
        $this->start($targets, $payer, $channel);
    }

    /**
     * Starts one payment for the targets not yet paid for, the first of which must not be. Any
     * pending payment for them is abandoned. A counter payment never reaches the gateway.
     *
     * @param  non-empty-list<Payable>  $targets
     */
    public function start(array $targets, User $payer, ?PaymentChannel $channel = null): Payment
    {
        $named = $targets[0];
        $purpose = $named->paymentPurpose();

        $named->assertPayable();
        $this->refuseIfPaid($named);

        $targets = $this->unpaid($purpose, $targets);
        $amount = array_sum(array_map(fn(Payable $t) => $t->amountKobo(), $targets));
        $reference = $this->newReference();

        $checkout = $channel === PaymentChannel::Counter
            ? null
            : $this->checkout($reference, $amount, $payer, $channel, $purpose, $targets);

        return DB::transaction(function () use ($named, $purpose, $targets, $payer, $channel, $amount, $reference, $checkout) {
            $ids = array_map(fn(Payable $t) => $t->paymentTargetId(), $targets);

            $this->settlement->abandonPendingFor($purpose, $ids);
            $this->refuseIfPaid($named);

            $payment = Payment::create([
                'reference' => $reference,
                'user_id' => $payer->id,
                'purpose' => $purpose,
                'channel' => $channel,
                'status' => PaymentStatus::Pending,
                'amount_kobo' => $amount,
                'authorization_url' => $checkout?->authorizationUrl,
                'access_code' => $checkout?->accessCode,
            ]);

            foreach ($targets as $target) {
                $payment
                    ->targets()
                    ->create([
                        'purpose' => $purpose,
                        'target_id' => $target->paymentTargetId(),
                        'target_reference' => $target->paymentReference(),
                        'partner_name' => $target->paymentPartnerName(),
                        'amount_kobo' => $target->amountKobo(),
                        'state' => PaymentShareState::Standing,
                    ]);
            }

            return $payment->load('targets');
        });
    }

    public function summaryFor(Payable $target): ?array
    {
        return $this->summariesFor($target->paymentPurpose(), [$target->paymentTargetId()])[$target->paymentTargetId()] ?? null;
    }

    public function summariesFor(PaymentPurpose $purpose, array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return $this->shares
            ->currentOf($purpose, $targetIds)
            ->mapWithKeys(fn(PaymentTarget $share) => [$share->target_id => PaymentSummary::ofShare($share)->toArray()])
            ->all();
    }

    public function openAtCounter(Payable $target): void
    {
        $this->start([$target], $target->payer(), PaymentChannel::Counter);
    }

    /**
     * Marks a counter payment paid when the pharmacy hands the order over.
     */
    public function collectAtCounter(Payable $target): void
    {
        $share = $this->shares->current($target->paymentPurpose(), $target->paymentTargetId());

        if ($share === null || ! $share->payment->atCounter()) {
            throw ApiException::unprocessable('NOT_A_COUNTER_PAYMENT');
        }

        $this->settlement->paidAtCounter($share->payment);
    }

    public function refund(Payable $target, ?int $amountKobo = null): void
    {
        $this->refunds->refund($target, $amountKobo);
    }

    /**
     * The payer's own payment by reference; anyone else's is not found. A pending one is checked
     * with the gateway at most every few seconds, in case its webhook is late.
     */
    public function forPayer(User $payer, string $reference): Payment
    {
        $payment = Payment::query()
            ->where('reference', $reference)
            ->where('user_id', $payer->id)
            ->first();

        if ($payment === null) {
            throw ApiException::notFound(Payment::NOT_FOUND);
        }

        $payment = $this->settlement->refresh($payment);

        return $payment->load('targets');
    }

    private function refuseIfPaid(Payable $target): void
    {
        if ($this->shares->paid($target->paymentPurpose(), [$target->paymentTargetId()])->isNotEmpty()) {
            throw ApiException::unprocessable('ALREADY_PAID');
        }
    }

    /**
     * @param  list<Payable>  $targets
     * @return non-empty-list<Payable>
     */
    private function unpaid(PaymentPurpose $purpose, array $targets): array
    {
        $all = collect($targets)->unique(fn(Payable $t) => $t->paymentTargetId());

        $paid = $this->shares
            ->paid($purpose, $all->map(fn(Payable $t) => $t->paymentTargetId())->values()->all())
            ->pluck('target_id')
            ->all();

        return $all
            ->reject(fn(Payable $t) => in_array($t->paymentTargetId(), $paid, true))
            ->values()
            ->all();
    }

    /**
     * @param  list<Payable>  $targets
     */
    private function checkout(string $reference, int $amount, User $payer, ?PaymentChannel $channel, PaymentPurpose $purpose, array $targets): GatewayCheckout
    {
        if (! $this->gateway->configured()) {
            throw ApiException::unprocessable('PAYMENTS_UNAVAILABLE');
        }

        $callback = config('elevenmed.payments.callback_url');
        $separator = str_contains($callback, '?') ? '&' : '?';

        try {
            return $this->gateway->initialise(
                $reference,
                $amount,
                $this->emailFor($payer),
                $channel === null ? [] : [$channel->value],
                $callback . $separator . 'reference=' . rawurlencode($reference),
                [
                    'purpose' => $purpose->value,
                    'target_ids' => array_map(fn(Payable $t) => $t->paymentTargetId(), $targets),
                ],
            );
        } catch (GatewayUnavailable $e) {
            report($e);

            throw new ApiException(HttpStatus::SERVICE_UNAVAILABLE, 'PAYMENT_GATEWAY_UNAVAILABLE');
        }
    }

    /**
     * Paystack needs an email; many accounts have none, so those get one made from the account id.
     */
    private function emailFor(User $payer): string
    {
        return filled($payer->email)
            ? $payer->email
            : $payer->uuid . '@' . config('elevenmed.payments.email_domain');
    }

    private function newReference(): string
    {
        return config('elevenmed.payments.reference_prefix') . Str::upper(Str::random(16));
    }
}

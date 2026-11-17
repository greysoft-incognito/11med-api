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
 * Starting payments and reading them (app handover §16). Settling them is `Settlement`'s, refunding
 * them `Refunds`'.
 */
class Payments
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Settlement $settlement,
        private readonly PaymentShares $shares,
    ) {}

    /**
     * Starts, or restarts, paying for a target and everything one payment for it covers. Any pending
     * payment for the same targets is abandoned. A counter payment never reaches the gateway.
     */
    public function initialise(Payable $target, User $patient, ?PaymentChannel $channel = null): Payment
    {
        $target->ensurePayable();

        if ($this->shares->paid($target->paymentPurpose(), [$target->paymentTargetId()])->isNotEmpty()) {
            throw ApiException::unprocessable('ALREADY_PAID');
        }

        $targets = $this->unpaid($target);
        $amount = array_sum(array_map(fn(Payable $t) => $t->paymentAmountKobo(), $targets));
        $reference = $this->newReference();

        $checkout = $channel === PaymentChannel::Counter
            ? null
            : $this->checkout($reference, $amount, $patient, $channel, $target->paymentPurpose(), $targets);

        return DB::transaction(function () use ($target, $targets, $patient, $channel, $amount, $reference, $checkout) {
            $ids = array_map(fn(Payable $t) => $t->paymentTargetId(), $targets);

            $this->settlement->abandonPendingFor($target->paymentPurpose(), $ids);

            if ($this->shares->paid($target->paymentPurpose(), [$target->paymentTargetId()])->isNotEmpty()) {
                throw ApiException::unprocessable('ALREADY_PAID');
            }

            $payment = Payment::create([
                'reference' => $reference,
                'user_id' => $patient->id,
                'purpose' => $target->paymentPurpose(),
                'channel' => $channel,
                'status' => PaymentStatus::Pending,
                'amount_kobo' => $amount,
                'authorization_url' => $checkout?->authorizationUrl,
                'access_code' => $checkout?->accessCode,
            ]);

            foreach ($targets as $each) {
                $payment
                    ->targets()
                    ->create([
                        'purpose' => $each->paymentPurpose(),
                        'target_id' => $each->paymentTargetId(),
                        'target_reference' => $each->paymentTargetReference(),
                        'partner_name' => $each->paymentPartnerName(),
                        'amount_kobo' => $each->paymentAmountKobo(),
                        'state' => PaymentShareState::Standing,
                    ]);
            }

            return $payment->load('targets');
        });
    }

    /**
     * Marks a counter payment paid when the pharmacy hands the order over.
     */
    public function settleAtCounter(Payable $target): Payment
    {
        $share = $this->shares->current($target->paymentPurpose(), $target->paymentTargetId());

        if ($share === null || ! $share->payment->atCounter()) {
            throw ApiException::unprocessable('NOT_A_COUNTER_PAYMENT');
        }

        return $this->settlement->paidAtCounter($share->payment);
    }

    /**
     * The patient's own payment by reference; anyone else's is not found. A pending one is checked
     * with the gateway at most every few seconds, in case its webhook is late.
     */
    public function forPatient(User $patient, string $reference): Payment
    {
        $payment = Payment::query()
            ->where('reference', $reference)
            ->where('user_id', $patient->id)
            ->first();

        if ($payment === null) {
            throw ApiException::notFound(Payment::NOT_FOUND);
        }

        $payment = $this->settlement->refresh($payment);

        return $payment->load('targets');
    }

    public function summaryFor(Payable $target): ?PaymentSummary
    {
        return $this->summariesFor($target->paymentPurpose(), [$target->paymentTargetId()])[$target->paymentTargetId()] ?? null;
    }

    /**
     * Each target's summary in one query: its standing paid share when there is one, else its newest.
     *
     * @param  list<string>  $targetIds
     * @return array<string, PaymentSummary>
     */
    public function summariesFor(PaymentPurpose $purpose, array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return $this->shares
            ->currentOf($purpose, $targetIds)
            ->mapWithKeys(fn(PaymentTarget $share) => [$share->target_id => PaymentSummary::ofShare($share)])
            ->all();
    }

    /**
     * The targets one payment covers, less any already paid for.
     *
     * @return list<Payable>
     */
    private function unpaid(Payable $target): array
    {
        $all = collect($target->paymentTargets())
            ->push($target)
            ->unique(fn(Payable $t) => $t->paymentTargetId());

        $paid = $this->shares->paid($target->paymentPurpose(), $all->map(fn(Payable $t) => $t->paymentTargetId())->values()->all())
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
    private function checkout(string $reference, int $amount, User $patient, ?PaymentChannel $channel, PaymentPurpose $purpose, array $targets): GatewayCheckout
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
                $this->emailFor($patient),
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
    private function emailFor(User $patient): string
    {
        return filled($patient->email)
            ? $patient->email
            : $patient->uuid . '@' . config('elevenmed.payments.email_domain');
    }

    private function newReference(): string
    {
        return config('elevenmed.payments.reference_prefix') . Str::upper(Str::random(16));
    }
}

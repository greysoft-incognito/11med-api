<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Payments\Gateway\GatewayVerdict;
use App\Services\Payments\Gateway\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Paystack's webhook, the authority on payment (app handover §16). The signature is checked on every
 * call; events for references this API never issued are acknowledged and ignored.
 */
class PaystackWebhook
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Settlement $settlement,
        private readonly Refunds $refunds,
    ) {}

    public function handle(string $payload, ?string $signature): void
    {
        if (! $this->gateway->signatureIsValid($payload, $signature)) {
            throw ApiException::unauthorized('INVALID_SIGNATURE');
        }

        $body = json_decode($payload, true);
        $event = is_array($body) ? (string) ($body['event'] ?? '') : '';
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        match ($event) {
            'charge.success' => $this->charge($data),
            'refund.processed' => $this->refund($data, RefundStatus::Processed),
            'refund.failed' => $this->refund($data, RefundStatus::Failed),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function charge(array $data): void
    {
        $payment = $this->payment($data['reference'] ?? null);

        if ($payment === null) {
            return;
        }

        $this->settlement->apply($payment, GatewayVerdict::fromPaystack($data));
    }

    /**
     * Finds the refund by the gateway's id, else the oldest open one of that amount on the transaction.
     *
     * @param  array<string, mixed>  $data
     */
    private function refund(array $data, RefundStatus $outcome): void
    {
        $payment = $this->payment($data['transaction_reference'] ?? null);

        if ($payment === null) {
            return;
        }

        $refund = $payment
            ->refunds()
            ->where('status', RefundStatus::Pending->value)
            ->when(
                isset($data['id']) && $payment->refunds()->where('gateway_refund_id', (string) $data['id'])->exists(),
                fn($q) => $q->where('gateway_refund_id', (string) $data['id']),
                fn($q) => $q->where('amount_kobo', (int) ($data['amount'] ?? 0)),
            )
            ->oldest('id')
            ->first();

        if (! $refund instanceof PaymentRefund) {
            Log::warning('Paystack refund event matched no open refund.', ['reference' => $payment->reference]);

            return;
        }

        $outcome === RefundStatus::Processed
            ? $this->refunds->confirm($refund)
            : $this->refunds->fail($refund, $data['refund_reason'] ?? $data['merchant_note'] ?? null);
    }

    private function payment(mixed $reference): ?Payment
    {
        $payment = is_string($reference)
            ? Payment::query()->where('reference', $reference)->first()
            : null;

        if ($payment === null) {
            Log::info('Paystack event for an unknown reference.');
        }

        return $payment;
    }
}

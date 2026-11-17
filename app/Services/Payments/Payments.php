<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * What other modules ask of payments (app handover §16). The Payments module (M9) implements it,
 * owns the payments table and is told of settlement only by the gateway's webhook; owners of a
 * Payable listen for `PaymentSucceeded` and `PaymentRefunded`.
 *
 * A summary is the `zPaymentSummary` shape: `reference`, `status`, `channel`, `amount_kobo`,
 * `authorization_url` (while pending, never at the counter) and `paid_at`.
 */
#[Bind(GatewayPayments::class)]
interface Payments
{
    /**
     * Starts one payment, with one reference, for every target in the list, abandoning any pending
     * payment for them. A counter payment never reaches the gateway. Read the result, with its
     * `authorization_url`, through `summaryFor`.
     *
     * @param  non-empty-list<Payable>  $targets
     */
    public function initialise(array $targets, User $payer, ?PaymentChannel $channel = null): void;

    /**
     * The summary a target's resource carries, or null when no payment was started.
     *
     * @return array<string, mixed>|null
     */
    public function summaryFor(Payable $target): ?array;

    /**
     * The summaries for a list of targets in one query, keyed by target id; a target with no
     * payment is absent.
     *
     * @param  list<string>  $targetIds
     * @return array<string, array<string, mixed>>
     */
    public function summariesFor(PaymentPurpose $purpose, array $targetIds): array;

    /**
     * Opens a pending counter payment for a pickup paid when collected
     * (`initialise([$target], payer, Counter)`).
     */
    public function openAtCounter(Payable $target): void;

    /**
     * Marks a counter payment paid when the pickup is collected.
     */
    public function collectAtCounter(Payable $target): void;

    /**
     * Refunds the target's share (all of it by default). A pending share is released and reads
     * `abandoned`; a paid one reads `refunded` once the gateway confirms. Nothing happens when no
     * payment was started.
     */
    public function refund(Payable $target, ?int $amountKobo = null): void;
}

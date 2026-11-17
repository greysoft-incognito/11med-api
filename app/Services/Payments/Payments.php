<?php

namespace App\Services\Payments;

use Illuminate\Container\Attributes\Bind;

/**
 * What other modules ask of payments (app handover §16). The Payments module (M9) implements it,
 * owns the payments table and is told of settlement only by the gateway's webhook; owners of a
 * Payable listen for `PaymentSucceeded` and `PaymentRefunded`.
 */
#[Bind(NoPayments::class)]
interface Payments
{
    /**
     * The `zPaymentSummary` a target's resource carries, or null when no payment was started.
     *
     * @return array<string, mixed>|null
     */
    public function summaryFor(Payable $target): ?array;

    /**
     * Opens a pending counter payment for a pickup paid when collected.
     */
    public function openAtCounter(Payable $target): void;

    /**
     * Marks a counter payment paid when the pickup is collected.
     */
    public function collectAtCounter(Payable $target): void;

    /**
     * Refunds this target's share of its payment in full. Nothing happens when it was never paid;
     * the summary reads `refunded` once the gateway confirms.
     */
    public function refund(Payable $target): void;
}

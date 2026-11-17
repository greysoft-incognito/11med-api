<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Models\User;

/**
 * No payment is ever started until the Payments module exists.
 */
class NoPayments implements Payments
{
    public function initialise(array $targets, User $payer, ?PaymentChannel $channel = null): void {}

    public function summaryFor(Payable $target): ?array
    {
        return null;
    }

    public function summariesFor(PaymentPurpose $purpose, array $targetIds): array
    {
        return [];
    }

    public function openAtCounter(Payable $target): void {}

    public function collectAtCounter(Payable $target): void {}

    public function refund(Payable $target, ?int $amountKobo = null): void {}
}

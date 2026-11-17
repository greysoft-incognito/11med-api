<?php

namespace App\Services\Payments;

/**
 * No payment is ever started until the Payments module exists.
 */
class NoPayments implements Payments
{
    public function summaryFor(Payable $target): ?array
    {
        return null;
    }

    public function openAtCounter(Payable $target): void {}

    public function collectAtCounter(Payable $target): void {}

    public function refund(Payable $target): void {}
}

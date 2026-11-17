<?php

namespace App\Services\Payments;

use App\Models\User;

/**
 * Finds what `POST payments` is paying for. One implementation per purpose, named in
 * `elevenmed.payments.targets` by the module that owns the record.
 */
interface PayableTargets
{
    /**
     * Every target one payment covers, the named one first: for an order, the other unpaid orders
     * of the same checkout. Throws 404 for a target that is not the payer's.
     *
     * @return non-empty-list<Payable>
     */
    public function resolve(string $targetId, User $payer): array;
}

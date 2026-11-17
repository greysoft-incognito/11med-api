<?php

namespace App\Events\Payments;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment cleared. The target's module tells its partner or doctor and sends the receipt. Listeners filter on `$payment->purpose`.
 */
class PaymentSucceeded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
    ) {}
}

<?php

namespace App\Events\Payments;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The gateway refused a payment. Listeners filter on `$payment->purpose`.
 */
class PaymentFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
    ) {}
}

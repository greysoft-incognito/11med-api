<?php

namespace App\Events\Payments;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A pending payment was given up: restarted, timed out, or every target it covered was cancelled. Listeners filter on `$payment->purpose`.
 */
class PaymentAbandoned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
    ) {}
}

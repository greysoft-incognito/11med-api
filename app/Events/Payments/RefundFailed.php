<?php

namespace App\Events\Payments;

use App\Models\PaymentRefund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The gateway would not return a refund; the money stays with us until the team acts.
 */
class RefundFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentRefund $refund,
    ) {}
}

<?php

namespace App\Events\Payments;

use App\Enums\PaymentPurpose;
use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The gateway confirmed a refund of one target's share.
 */
class PaymentRefunded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentPurpose $purpose,
        public readonly string $targetId,
        public readonly int $amountKobo,
    ) {}
}

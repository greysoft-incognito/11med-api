<?php

namespace App\Events\Payments;

use App\Services\Payments\Payable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The gateway confirmed a refund of this target's share.
 */
class PaymentRefunded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payable $target,
        public readonly string $reference,
        public readonly int $amountKobo,
    ) {}
}

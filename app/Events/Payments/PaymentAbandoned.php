<?php

namespace App\Events\Payments;

use App\Services\Payments\Payable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * This target's pending payment was given up: restarted, timed out, or the target cancelled before it was paid. Announced once per target.
 */
class PaymentAbandoned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payable $target,
        public readonly string $reference,
    ) {}
}

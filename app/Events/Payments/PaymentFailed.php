<?php

namespace App\Events\Payments;

use App\Services\Payments\Payable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The gateway refused this target's payment. Announced once per target.
 */
class PaymentFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payable $target,
        public readonly string $reference,
    ) {}
}

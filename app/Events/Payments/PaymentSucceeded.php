<?php

namespace App\Events\Payments;

use App\Enums\PaymentChannel;
use App\Services\Payments\Payable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A target is paid, announced once per target after the gateway confirms (or at the counter).
 */
class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly Payable $target,
        public readonly string $reference,
        public readonly PaymentChannel $channel,
    ) {}
}

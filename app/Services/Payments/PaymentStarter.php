<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\User;

/**
 * `POST payments`: the payer names a target by purpose and id; its module resolves it with what one
 * payment for it covers, and the payment starts. Only an order can be paid at the counter.
 */
class PaymentStarter
{
    public function __construct(
        private readonly PayableResolver $targets,
        private readonly GatewayPayments $payments,
    ) {}

    public function start(User $payer, PaymentPurpose $purpose, string $targetId, ?PaymentChannel $channel): Payment
    {
        $targets = $this->targets->resolve($purpose, $targetId, $payer);

        if ($channel === PaymentChannel::Counter && $purpose !== PaymentPurpose::Order) {
            throw ApiException::unprocessable('COUNTER_IS_FOR_PICKUP', 'channel');
        }

        return $this->payments->start($targets, $payer, $channel);
    }
}

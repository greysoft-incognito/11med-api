<?php

namespace App\Services\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\User;

/**
 * `POST payments`: the patient names a target by purpose and id; its module finds it and `Payments`
 * starts the payment. Only an order can be paid at the counter.
 */
class PaymentStarter
{
    public function __construct(
        private readonly PayableTargets $targets,
        private readonly Payments $payments,
    ) {}

    public function start(User $patient, PaymentPurpose $purpose, string $targetId, ?PaymentChannel $channel): Payment
    {
        $target = $this->targets->find($purpose, $patient, $targetId);

        if ($channel === PaymentChannel::Counter && $purpose !== PaymentPurpose::Order) {
            throw ApiException::unprocessable('COUNTER_IS_FOR_PICKUP', 'channel');
        }

        return $this->payments->initialise($target, $patient, $channel);
    }
}

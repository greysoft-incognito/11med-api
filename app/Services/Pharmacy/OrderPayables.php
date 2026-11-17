<?php

namespace App\Services\Pharmacy;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PayableTargets;

/**
 * What `POST payments` with purpose `order` pays for: the named order, then the other unpaid
 * orders of its checkout, so a second pharmacy never waits on a second payment.
 */
class OrderPayables implements PayableTargets
{
    public function resolve(string $targetId, User $payer): array
    {
        $order = Order::query()
            ->with(PatientOrders::RELATIONS)
            ->where('patient_id', $payer->id)
            ->where('uuid', $targetId)
            ->first();

        if ($order === null) {
            throw ApiException::notFound(Order::NOT_FOUND);
        }

        $siblings = Order::query()
            ->with(PatientOrders::RELATIONS)
            ->where('checkout_id', $order->checkout_id)
            ->whereKeyNot($order->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereNotIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Refunded])
            ->orderBy('id')
            ->get();

        return [$order, ...$siblings];
    }
}

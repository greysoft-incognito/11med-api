<?php

namespace App\Services\Pharmacy;

use App\Enums\PaymentPurpose;
use App\Models\Order;
use App\Services\Payments\Payments;
use Illuminate\Support\Collection;

/**
 * Puts each order's `zPaymentSummary` on it, as the `payment` relation its resources read. Lists
 * read every summary in one call; an order whose payment never started reads its own mirror.
 */
class OrderPayments
{
    public function __construct(
        private readonly Payments $payments,
    ) {}

    /**
     * @template T of Collection<int, Order>|Order
     *
     * @param  T  $orders
     * @return T
     */
    public function attach(Collection|Order $orders): Collection|Order
    {
        $list = $orders instanceof Order ? collect([$orders]) : $orders;

        $summaries = $list->isEmpty()
            ? []
            : $this->payments->summariesFor(PaymentPurpose::Order, $list->pluck('uuid')->all());

        $list->each(fn(Order $order) => $order->setRelation('payment', $summaries[$order->uuid] ?? $this->mirror($order)));

        return $orders;
    }

    /**
     * @return array<string, mixed>
     */
    private function mirror(Order $order): array
    {
        return [
            'reference' => $order->reference,
            'status' => $order->payment_status->value,
            'channel' => $order->payment_channel?->value,
            'amount_kobo' => $order->total_kobo,
            'authorization_url' => null,
            'paid_at' => null,
        ];
    }
}

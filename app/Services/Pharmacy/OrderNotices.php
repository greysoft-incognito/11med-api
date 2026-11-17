<?php

namespace App\Services\Pharmacy;

use App\Enums\Fulfilment;
use App\Enums\NotificationCategory;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Notifications\Notifier;

/**
 * What the patient is told as their order moves (app handover §8.2, mock `notice()`): what
 * happened and what to do, never what was bought.
 */
class OrderNotices
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function moved(Order $order): void
    {
        $delivery = $order->fulfilment === Fulfilment::Delivery;
        $reference = $order->reference;

        [$title, $body] = match ($order->status) {
            OrderStatus::Preparing => [
                __('pharmacy.push.preparing.title'),
                __('pharmacy.push.preparing.' . ($delivery ? 'delivery' : 'pickup'), ['reference' => $reference]),
            ],
            OrderStatus::Ready => $delivery
                ? [__('pharmacy.push.ready.delivery_title'), __('pharmacy.push.ready.delivery', [
                    'reference' => $reference,
                    'rider' => $order->rider['name'],
                    'minutes' => max(1, (int) round(now()->diffInMinutes($order->estimated_delivery_at, false))),
                ])]
                : [__('pharmacy.push.ready.pickup_title'), __('pharmacy.push.ready.pickup', ['reference' => $reference, 'pharmacy' => $order->organisation->name])],
            OrderStatus::Completed => [
                __('pharmacy.push.completed.' . ($delivery ? 'delivery_title' : 'pickup_title')),
                __('pharmacy.push.completed.body', ['reference' => $reference]),
            ],
            default => [null, null],
        };

        if ($title !== null) {
            $this->send($order, $title, $body);
        }
    }

    public function cancelled(Order $order): void
    {
        $this->send($order, __('pharmacy.push.cancelled.title'), __('pharmacy.push.cancelled.body', ['reference' => $order->reference, 'reason' => $order->cancel_reason]));
    }

    private function send(Order $order, string $title, string $body): void
    {
        $this->notifier->send($order->patient, NotificationCategory::Order, $title, $body, [
            'target_id' => $order->uuid,
            'dedupe_key' => "order:{$order->uuid}:{$order->status->value}",
        ]);
    }
}

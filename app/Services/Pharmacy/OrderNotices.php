<?php

namespace App\Services\Pharmacy;

use App\Enums\Fulfilment;
use App\Enums\NotificationCategory;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Notifications\Notifier;

/**
 * What the patient is told as their order moves (app handover §8.2, mock `notice()`), and what the
 * pharmacy's accounts are told when an order reaches them or is called off: what happened and what
 * to do, never what was bought.
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
        $key = $order->cancelled_by === 'team' ? 'pharmacy.push.team_cancelled' : 'pharmacy.push.cancelled';

        $this->send($order, __("{$key}.title"), __("{$key}.body", ['reference' => $order->reference, 'reason' => $order->cancel_reason]));
    }

    /**
     * A paid order, or a pickup to be paid at the counter, is now the pharmacy's to start.
     */
    public function arrived(Order $order): void
    {
        $body = $order->isAtCounter() ? 'counter_body' : 'paid_body';

        $this->notifier->toOrganisation(
            $order->organisation,
            NotificationCategory::PharmacyOrder,
            __('notifications.pharmacy_order.arrived_title', ['reference' => $order->reference]),
            __("notifications.pharmacy_order.{$body}"),
            ['target_id' => $order->uuid, 'dedupe_key' => "pharmacy_order_arrived:{$order->uuid}"],
        );
    }

    /**
     * The patient called off an order the pharmacy could already see.
     */
    public function withdrawn(Order $order): void
    {
        $this->notifier->toOrganisation(
            $order->organisation,
            NotificationCategory::PharmacyOrder,
            __('notifications.pharmacy_order.cancelled_title', ['reference' => $order->reference]),
            __('notifications.pharmacy_order.cancelled_body'),
            ['target_id' => $order->uuid, 'dedupe_key' => "pharmacy_order_cancelled:{$order->uuid}"],
        );
    }

    private function send(Order $order, string $title, string $body): void
    {
        $this->notifier->send($order->patient, NotificationCategory::Order, $title, $body, [
            'target_id' => $order->uuid,
            'dedupe_key' => "order:{$order->uuid}:{$order->status->value}",
        ]);
    }
}

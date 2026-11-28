<?php

namespace App\Listeners\Pharmacy;

use App\Enums\NotificationCategory;
use App\Enums\PaymentStatus;
use App\Events\Payments\PaymentRefunded;
use App\Events\Payments\PaymentSucceeded;
use App\Models\Order;
use App\Services\Notifications\Notifier;
use App\Services\Pharmacy\OrderNotices;

/**
 * The payment events write the order's mirror, which is what lets its pharmacy see it; the patient
 * gets one receipt per payment, however many orders it covered, and the pharmacy is told the order
 * has arrived.
 */
class RecordOrderPayment
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly OrderNotices $notices,
    ) {}

    public function handleSucceeded(PaymentSucceeded $event): void
    {
        if (! $event->target instanceof Order) {
            return;
        }

        Order::query()
            ->whereKey($event->target->id)
            ->update(['payment_status' => PaymentStatus::Paid, 'payment_channel' => $event->channel]);

        $this->notifier->send(
            $event->target->payer(),
            NotificationCategory::Order,
            __('pharmacy.push.paid.title'),
            __('pharmacy.push.paid.body', ['reference' => $event->target->reference]),
            ['target_id' => $event->target->uuid, 'dedupe_key' => 'payment:' . $event->reference]
        );

        if (! $event->target->isAtCounter()) {
            $this->notices->arrived($event->target);
        }
    }

    public function handleRefunded(PaymentRefunded $event): void
    {
        if (! $event->target instanceof Order) {
            return;
        }

        Order::query()
            ->whereKey($event->target->id)
            ->update(['payment_status' => PaymentStatus::Refunded]);
    }
}

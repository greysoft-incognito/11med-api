<?php

namespace App\Enums;

/**
 * Where one target's share of a payment stands. `standing` follows the payment; `released` is a
 * target cancelled before it was paid for; the refund states follow the gateway.
 */
enum PaymentShareState: string
{
    case Standing = 'standing';
    case Released = 'released';
    case RefundPending = 'refund_pending';
    case RefundFailed = 'refund_failed';
    case Refunded = 'refunded';

    /**
     * The status this share shows on its target, given the payment's.
     */
    public function shownAs(PaymentStatus $payment): PaymentStatus
    {
        return match ($this) {
            self::Released => PaymentStatus::Abandoned,
            self::Refunded => PaymentStatus::Refunded,
            default => $payment,
        };
    }

    /**
     * The same rule in SQL, over `payment_targets.state` and `payments.status`.
     */
    public static function shownAsSql(): string
    {
        return "CASE payment_targets.state WHEN 'released' THEN 'abandoned' WHEN 'refunded' THEN 'refunded' ELSE payments.status END";
    }
}

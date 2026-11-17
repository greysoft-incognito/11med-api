<?php

namespace App\Enums;

/**
 * A payment's state (app `zPaymentStatus`). Only `pending` moves, except that a paid payment becomes
 * `refunded` once every share it covered is refunded.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case Refunded = 'refunded';
}

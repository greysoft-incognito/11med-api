<?php

namespace App\Enums;

/**
 * Where a payment stands (app `zPaymentStatus`).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case Refunded = 'refunded';
}

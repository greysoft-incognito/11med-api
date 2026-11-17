<?php

namespace App\Enums;

/**
 * A refund's state: asked of the gateway, then confirmed or refused by its webhook.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
}

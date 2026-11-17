<?php

namespace App\Enums;

/**
 * `zSubscription.status`. A recorded premium never renews, so only `none` and `active` occur;
 * `cancelled` and `past_due` stay for a seller that bills.
 */
enum SubscriptionStatus: string
{
    case None = 'none';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
}

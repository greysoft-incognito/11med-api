<?php

namespace App\Enums;

/**
 * A doctor's payout (app and portal `zPayoutStatus`). A failed payout stays owed: its
 * consultations return to the next one.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}

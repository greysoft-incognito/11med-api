<?php

namespace App\Enums;

/**
 * Where an announcement stands: waiting for its time, going out, out, or withdrawn before it went.
 */
enum AnnouncementStatus: string
{
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
}

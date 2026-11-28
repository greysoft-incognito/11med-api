<?php

namespace App\Enums;

/**
 * A support ticket's state: `open` waits for the team, `answered` for the requester.
 */
enum SupportStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';
}

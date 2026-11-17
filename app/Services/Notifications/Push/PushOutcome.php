<?php

namespace App\Services\Notifications\Push;

/**
 * What a push service answered for one device token.
 */
enum PushOutcome
{
    case Delivered;
    case InvalidToken;
    case Failed;
}

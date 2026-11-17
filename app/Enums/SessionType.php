<?php

namespace App\Enums;

/**
 * How a consultation is held (app `zSessionType`). Each is priced on its own (§7.1).
 */
enum SessionType: string
{
    case Chat = 'chat';
    case Audio = 'audio';
    case Video = 'video';

    public function isCall(): bool
    {
        return $this !== self::Chat;
    }
}

<?php

namespace App\Enums;

/**
 * Which client a token pair was issued to. A refresh token only rotates for its own client.
 */
enum AuthClient: string
{
    case App = 'app';
    case Portal = 'portal';

    /**
     * The code a failed refresh answers with, as each client spells it.
     */
    public function refreshFailure(): string
    {
        return match ($this) {
            self::App => 'AUTH_SESSION_EXPIRED',
            self::Portal => 'INVALID_REFRESH',
        };
    }
}

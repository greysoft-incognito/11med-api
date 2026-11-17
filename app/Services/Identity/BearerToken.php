<?php

namespace App\Services\Identity;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tells an expired access token from a bad one, so a 401 can say `TOKEN_EXPIRED`.
 */
final class BearerToken
{
    public static function isExpired(Request $request): bool
    {
        $bearer = $request->bearerToken();

        if ($bearer === null) {
            return false;
        }

        $token = PersonalAccessToken::findToken($bearer);

        return $token?->expires_at !== null && $token->expires_at->isPast();
    }
}

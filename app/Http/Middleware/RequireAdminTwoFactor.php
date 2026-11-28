<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\Identity\TwoFactor\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the team's desk closed to an admin without two-step verification while it is required.
 * Their own account routes stay open, so they can set it up.
 */
class RequireAdminTwoFactor
{
    public function __construct(
        private readonly TwoFactorPolicy $policy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/portal/admin', 'api/v1/portal/admin/*')) {
            $user = $request->user('sanctum');

            if ($user !== null && $this->policy->missing($user)) {
                throw ApiException::forbidden('TWO_FACTOR_SETUP_REQUIRED');
            }
        }

        return $next($request);
    }
}

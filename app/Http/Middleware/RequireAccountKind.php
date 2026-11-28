<?php

namespace App\Http\Middleware;

use App\Enums\AccountKind;
use App\Enums\AuthClient;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * `kind:patient,doctor` or `kind:staff`: the account must hold one of the named kinds (`staff`
 * is staff access), and the session must be for that kind's client, so an app token never
 * reaches a portal route and a portal token never reaches the app's.
 */
class RequireAccountKind
{
    public const STAFF = 'staff';

    public function handle(Request $request, Closure $next, string ...$kinds): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw ApiException::unauthorized('UNAUTHENTICATED');
        }

        foreach ($kinds as $kind) {
            [$holds, $client] = $kind === self::STAFF
                ? [$user->isStaff(), AuthClient::Portal]
                : [$user->holds(AccountKind::from($kind)), AccountKind::from($kind)->client()];

            if ($holds && $this->issuedTo($user, $client)) {
                return $next($request);
            }
        }

        // The kit's admin routes sit outside /api/v1, where only an HTTP exception renders.
        throw $request->is('api/v1/*') ? ApiException::forbidden() : new AccessDeniedHttpException;
    }

    private function issuedTo(User $user, AuthClient $client): bool
    {
        $token = $user->currentAccessToken();

        return ! $token instanceof PersonalAccessToken || $token->can($client->value);
    }

    /**
     * The middleware string for these kinds, with `staff` when `$staff` is set.
     *
     * @param  list<AccountKind>  $kinds
     */
    public static function for(array $kinds, bool $staff = false): string
    {
        $names = AccountKind::values($kinds);

        if ($staff) {
            $names[] = self::STAFF;
        }

        return 'kind:' . implode(',', $names);
    }

    public static function staff(): string
    {
        return 'kind:' . self::STAFF;
    }
}

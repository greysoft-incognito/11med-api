<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Identity\SignInCollection;
use App\Services\Identity\Sessions;
use App\Services\Identity\SessionTokens;
use Illuminate\Http\Request;

/**
 * The signed-in devices, for app and portal accounts alike.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly Sessions $sessions,
        private readonly SessionTokens $tokens,
    ) {}

    public function index(Request $request): SignInCollection
    {
        $page = $this->sessions->list($request->user(), ListQuery::from($request), $request->user()->currentAccessToken());

        return new SignInCollection($page);
    }

    public function destroy(Request $request, string $sessionId): EmptyResource
    {
        $this->sessions->end($request->user(), $sessionId);

        return new EmptyResource;
    }

    /**
     * Sign out everywhere else: the session making the call survives.
     */
    public function destroyOthers(Request $request): EmptyResource
    {
        $family = $this->tokens->familyOf($request->user()->currentAccessToken());

        $this->sessions->revokeAll($request->user(), $family);

        return new EmptyResource;
    }
}

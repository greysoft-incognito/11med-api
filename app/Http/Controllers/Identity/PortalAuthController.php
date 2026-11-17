<?php

namespace App\Http\Controllers\Identity;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\PortalForgotRequest;
use App\Http\Requests\Identity\PortalSignInRequest;
use App\Http\Requests\Identity\RefreshRequest;
use App\Http\Requests\Identity\SetPasswordRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Identity\LinkSentResource;
use App\Http\Resources\Identity\PortalAccountResource;
use App\Http\Resources\Identity\PortalSessionResource;
use App\Services\Identity\PortalAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The portals' sign-in (portals `src/api/mocks/handlers/auth.ts`).
 */
class PortalAuthController extends Controller
{
    public function __construct(
        private readonly PortalAccounts $accounts,
    ) {}

    public function signIn(PortalSignInRequest $request): JsonResponse
    {
        $session = $this->accounts->signIn(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return (new PortalSessionResource($session))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function session(Request $request): PortalAccountResource
    {
        $account = $request->user();

        return new PortalAccountResource($account);
    }

    public function signOut(Request $request): EmptyResource
    {
        $this->accounts->signOut($request->bearerToken());

        return new EmptyResource;
    }

    public function refresh(RefreshRequest $request): PortalSessionResource
    {
        $session = $this->accounts->refresh($request->string('refresh_token')->toString());

        return new PortalSessionResource($session);
    }

    public function forgot(PortalForgotRequest $request): LinkSentResource
    {
        $this->accounts->forgot($request->string('email')->toString());

        return new LinkSentResource;
    }

    public function setPassword(SetPasswordRequest $request): PortalSessionResource
    {
        $session = $this->accounts->setPassword(
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return new PortalSessionResource($session);
    }
}

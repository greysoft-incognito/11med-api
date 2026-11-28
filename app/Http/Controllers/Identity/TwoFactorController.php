<?php

namespace App\Http\Controllers\Identity;

use App\Enums\AuthClient;
use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\DisableTwoFactorRequest;
use App\Http\Requests\Identity\TwoFactorChallengeRequest;
use App\Http\Requests\Identity\TwoFactorConfirmRequest;
use App\Http\Requests\Identity\TwoFactorPasswordRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Identity\AppSessionResource;
use App\Http\Resources\Identity\PendingSecretResource;
use App\Http\Resources\Identity\PortalSessionResource;
use App\Http\Resources\Identity\RecoveryCodesResource;
use App\Http\Resources\Identity\TwoFactorStatusResource;
use App\Services\Identity\AppSession;
use App\Services\Identity\PortalSession;
use App\Services\Identity\TwoFactor\TwoFactorChallenges;
use App\Services\Identity\TwoFactor\TwoFactorSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Two-step verification with an authenticator app, for app and portal accounts alike, and the
 * second step of each client's sign-in.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorSetup $setup,
        private readonly TwoFactorChallenges $challenges,
    ) {}

    public function show(Request $request): TwoFactorStatusResource
    {
        $user = $request->user();

        return new TwoFactorStatusResource($user);
    }

    public function store(TwoFactorPasswordRequest $request): PendingSecretResource
    {
        $pending = $this->setup->begin($request->user(), $request->string('password')->toString());

        return new PendingSecretResource($pending);
    }

    public function confirm(TwoFactorConfirmRequest $request): RecoveryCodesResource
    {
        $codes = $this->setup->confirm($request->user(), $request->string('code')->toString());

        return new RecoveryCodesResource($codes);
    }

    public function destroy(DisableTwoFactorRequest $request): EmptyResource
    {
        $this->setup->disable(
            $request->user(),
            $request->string('password')->toString(),
            $request->string('code')->toString(),
        );

        return new EmptyResource;
    }

    public function recoveryCodes(TwoFactorPasswordRequest $request): RecoveryCodesResource
    {
        $codes = $this->setup->regenerateCodes($request->user(), $request->string('password')->toString());

        return new RecoveryCodesResource($codes);
    }

    public function verifyApp(TwoFactorChallengeRequest $request): AppSessionResource
    {
        [$user, $pair] = $this->challenges->complete(
            $request->string('challenge_token')->toString(),
            AuthClient::App,
            $request->string('code')->toString(),
        );

        $session = new AppSession($user, $pair);

        return new AppSessionResource($session);
    }

    public function verifyPortal(TwoFactorChallengeRequest $request): JsonResponse
    {
        [$account, $pair] = $this->challenges->complete(
            $request->string('challenge_token')->toString(),
            AuthClient::Portal,
            $request->string('code')->toString(),
        );

        $session = new PortalSession($account, $pair);

        return (new PortalSessionResource($session))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }
}

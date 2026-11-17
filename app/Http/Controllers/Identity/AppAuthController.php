<?php

namespace App\Http\Controllers\Identity;

use App\Enums\AuthClient;
use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Requests\Identity\LogoutRequest;
use App\Http\Requests\Identity\PhoneRequest;
use App\Http\Requests\Identity\RefreshRequest;
use App\Http\Requests\Identity\RegisterRequest;
use App\Http\Requests\Identity\VerifyOtpRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Identity\AppSessionResource;
use App\Http\Resources\Identity\OtpSentResource;
use App\Http\Resources\Identity\PendingAccountResource;
use App\Http\Resources\Identity\TokenPairResource;
use App\Services\Identity\AppAccounts;
use App\Services\Identity\AppSession;
use App\Services\Identity\SessionTokens;
use App\Services\Identity\TokenPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The app's sign-up and sign-in (app spec §2.1.2).
 */
class AppAuthController extends Controller
{
    public function __construct(
        private readonly AppAccounts $accounts,
        private readonly SessionTokens $tokens,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $pending = $this->accounts->register($request->validated());

        return (new PendingAccountResource($pending))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function verifyOtp(VerifyOtpRequest $request): AppSessionResource
    {
        $session = $this->accounts->verifyPhone(
            $request->string('phone_number')->toString(),
            $request->string('code')->toString(),
            $request->header('X-Install-Id'),
        );

        return new AppSessionResource($session);
    }

    public function resendOtp(PhoneRequest $request): OtpSentResource
    {
        $sent = $this->accounts->resendCode($request->string('phone_number')->toString());

        return new OtpSentResource($sent);
    }

    public function login(LoginRequest $request): AppSessionResource
    {
        $session = $this->accounts->login(
            $request->string('phone_number')->toString(),
            $request->string('password')->toString(),
            $request->string('device_id')->toString(),
        );

        return new AppSessionResource($session);
    }

    public function refresh(RefreshRequest $request): TokenPairResource
    {
        [, $pair] = $this->tokens->rotate($request->string('refresh_token')->toString(), AuthClient::App);

        return new TokenPairResource($pair);
    }

    /**
     * Ends the session the refresh token belongs to. Works with an expired access token.
     */
    public function logout(LogoutRequest $request): EmptyResource
    {
        $this->tokens->revokeByRefreshToken($request->string('refresh_token')->toString());

        return new EmptyResource;
    }

    /**
     * The signed-in account, for a cold start that holds only tokens.
     */
    public function session(Request $request): AppSessionResource
    {
        $pair = new TokenPair($request->bearerToken(), '', '');
        $session = new AppSession($request->user(), $pair);

        return new AppSessionResource($session);
    }
}

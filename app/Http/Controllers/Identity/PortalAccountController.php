<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangePortalEmailRequest;
use App\Http\Requests\Identity\ChangePortalPasswordRequest;
use App\Http\Requests\Identity\UpdatePortalAccountRequest;
use App\Http\Requests\Identity\VerifyEmailRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Identity\EmailCodeSentResource;
use App\Http\Resources\Identity\PortalAccountResource;
use App\Services\Identity\Passwords;
use App\Services\Identity\PortalProfile;

/**
 * A portal account's own page: its name, email and password.
 */
class PortalAccountController extends Controller
{
    public function __construct(
        private readonly PortalProfile $profile,
        private readonly Passwords $passwords,
    ) {}

    public function update(UpdatePortalAccountRequest $request): PortalAccountResource
    {
        $account = $this->profile->rename($request->user(), $request->string('name')->toString());

        return new PortalAccountResource($account);
    }

    public function email(ChangePortalEmailRequest $request): EmailCodeSentResource
    {
        $sent = $this->profile->requestEmailChange(
            $request->user(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return new EmailCodeSentResource($sent);
    }

    public function verifyEmail(VerifyEmailRequest $request): PortalAccountResource
    {
        $account = $this->profile->confirmEmailChange($request->user(), $request->string('code')->toString());

        return new PortalAccountResource($account);
    }

    /**
     * The current password is the proof; every other session ends.
     */
    public function password(ChangePortalPasswordRequest $request): EmptyResource
    {
        $this->passwords->change(
            $request->user(),
            $request->user()->currentAccessToken(),
            $request->string('current_password')->toString(),
            $request->string('new_password')->toString(),
        );

        return new EmptyResource;
    }
}

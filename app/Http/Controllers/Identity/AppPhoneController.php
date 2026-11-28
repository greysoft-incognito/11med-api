<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangePhoneRequest;
use App\Http\Requests\Identity\VerifyPhoneChangeRequest;
use App\Http\Resources\Identity\AccountResource;
use App\Http\Resources\Identity\OtpSentResource;
use App\Services\Identity\PhoneNumbers;

/**
 * Changing the app account's phone number, the number it signs in with.
 */
class AppPhoneController extends Controller
{
    public function __construct(
        private readonly PhoneNumbers $phones,
    ) {}

    public function update(ChangePhoneRequest $request): OtpSentResource
    {
        $sent = $this->phones->requestChange(
            $request->user(),
            $request->string('phone_number')->toString(),
            $request->string('password')->toString(),
        );

        return new OtpSentResource($sent);
    }

    public function verify(VerifyPhoneChangeRequest $request): AccountResource
    {
        $user = $this->phones->confirm($request->user(), $request->string('code')->toString());

        return new AccountResource($user);
    }
}

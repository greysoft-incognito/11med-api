<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangePasswordRequest;
use App\Http\Requests\Identity\PhoneRequest;
use App\Http\Requests\Identity\ResetPasswordRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Identity\ForgotPasswordResource;
use App\Services\Identity\Passwords;

class AppPasswordController extends Controller
{
    public function __construct(
        private readonly Passwords $passwords,
    ) {}

    public function forgot(PhoneRequest $request): ForgotPasswordResource
    {
        $sent = $this->passwords->forgot($request->string('phone_number')->toString());

        return new ForgotPasswordResource($sent);
    }

    public function reset(ResetPasswordRequest $request): EmptyResource
    {
        $this->passwords->reset(
            $request->string('phone_number')->toString(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        return new EmptyResource;
    }

    public function change(ChangePasswordRequest $request): EmptyResource
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

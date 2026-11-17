<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateEmailRequest;
use App\Http\Requests\Identity\VerifyEmailRequest;
use App\Http\Resources\Identity\AccountResource;
use App\Http\Resources\Identity\EmailCodeSentResource;
use App\Services\Identity\EmailAddresses;
use Illuminate\Http\Request;

class AppEmailController extends Controller
{
    public function __construct(
        private readonly EmailAddresses $emails,
    ) {}

    public function update(UpdateEmailRequest $request): AccountResource
    {
        $user = $this->emails->update($request->user(), $request->validated('email'));

        return new AccountResource($user);
    }

    public function verify(VerifyEmailRequest $request): AccountResource
    {
        $user = $this->emails->verify($request->user(), $request->string('code')->toString());

        return new AccountResource($user);
    }

    public function resend(Request $request): EmailCodeSentResource
    {
        $sent = $this->emails->resend($request->user());

        return new EmailCodeSentResource($sent);
    }
}

<?php

namespace App\Http\Controllers\Identity;

use App\Enums\HttpStatus;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\LinkPhoneRequest;
use App\Http\Requests\Identity\SocialAuthRequest;
use App\Http\Resources\Identity\PendingAccountResource;
use App\Http\Resources\Identity\SocialOutcomeResource;
use App\Services\Identity\Social\SocialAccounts;
use Illuminate\Http\JsonResponse;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAccounts $social,
    ) {}

    public function signIn(SocialAuthRequest $request): SocialOutcomeResource
    {
        $outcome = $this->social->signIn(
            $request->enum('provider', SocialProvider::class),
            $request->string('id_token')->toString(),
            $request->string('device_id')->toString(),
            $request->validated('full_name'),
        );

        return new SocialOutcomeResource($outcome);
    }

    public function linkPhone(LinkPhoneRequest $request): JsonResponse
    {
        $pending = $this->social->linkPhone($request->validated());

        return (new PendingAccountResource($pending))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }
}

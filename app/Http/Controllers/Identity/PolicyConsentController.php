<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\AcceptPolicyRequest;
use App\Http\Resources\Identity\AccountResource;
use App\Http\Resources\Identity\PortalAccountResource;
use App\Services\Identity\PolicyConsent;

/**
 * Accepting the terms and privacy policy in force, from the app or a partner portal.
 */
class PolicyConsentController extends Controller
{
    public function __construct(
        private readonly PolicyConsent $consent,
    ) {}

    public function app(AcceptPolicyRequest $request): AccountResource
    {
        $user = $this->consent->accept($request->user(), $request->string('policy_version')->toString());

        return new AccountResource($user);
    }

    public function portal(AcceptPolicyRequest $request): PortalAccountResource
    {
        $account = $this->consent->accept($request->user(), $request->string('policy_version')->toString());

        return new PortalAccountResource($account);
    }
}

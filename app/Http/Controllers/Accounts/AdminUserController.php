<?php

namespace App\Http\Controllers\Accounts;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Accounts\ListUsersRequest;
use App\Http\Requests\Accounts\ResendVerificationRequest;
use App\Http\Requests\Accounts\SuspendUserRequest;
use App\Http\Resources\Accounts\AccountCodeSentResource;
use App\Http\Resources\Accounts\AdminUserCollection;
use App\Http\Resources\Accounts\AdminUserResource;
use App\Services\Accounts\AccountActions;
use App\Services\Accounts\UserDirectory;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly UserDirectory $directory,
        private readonly AccountActions $actions,
    ) {}

    public function index(ListUsersRequest $request): AdminUserCollection
    {
        $role = Role::tryFrom((string) $request->validated('role'));
        $page = $this->directory->list($role, $request->validated('status'), $request->validated('q'), ListQuery::from($request));

        return new AdminUserCollection($page);
    }

    public function show(string $id): AdminUserResource
    {
        $user = $this->directory->find($id);

        return new AdminUserResource($user);
    }

    public function suspend(SuspendUserRequest $request, string $id): AdminUserResource
    {
        $user = $this->actions->suspend($request->user(), $id, $request->validated('reason'));

        return new AdminUserResource($user);
    }

    public function reinstate(Request $request, string $id): AdminUserResource
    {
        $user = $this->actions->reinstate($request->user(), $id);

        return new AdminUserResource($user);
    }

    public function signOut(Request $request, string $id): AdminUserResource
    {
        $user = $this->actions->signOut($request->user(), $id);

        return new AdminUserResource($user);
    }

    public function resendVerification(ResendVerificationRequest $request, string $id): AccountCodeSentResource
    {
        $sent = $this->actions->resendVerification($request->user(), $id, $request->validated('channel'));

        return new AccountCodeSentResource($sent);
    }

    public function sendPasswordReset(Request $request, string $id): AccountCodeSentResource
    {
        $sent = $this->actions->sendPasswordReset($request->user(), $id);

        return new AccountCodeSentResource($sent);
    }
}

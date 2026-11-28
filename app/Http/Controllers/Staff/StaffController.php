<?php

namespace App\Http\Controllers\Staff;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Staff\InviteStaffRequest;
use App\Http\Requests\Staff\StaffAccessRequest;
use App\Http\Requests\Staff\StaffSearchRequest;
use App\Http\Resources\Staff\StaffMemberCollection;
use App\Http\Resources\Staff\StaffMemberResource;
use App\Services\Staff\StaffAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        private readonly StaffAccounts $staff,
    ) {}

    public function index(StaffSearchRequest $request): StaffMemberCollection
    {
        $page = $this->staff->list($request->validated(), ListQuery::from($request));

        return new StaffMemberCollection($page);
    }

    public function show(string $id): StaffMemberResource
    {
        $member = $this->staff->find($id);

        return new StaffMemberResource($member);
    }

    public function store(InviteStaffRequest $request): JsonResponse
    {
        $member = $this->staff->invite(
            $request->user(),
            $request->validated('email'),
            $request->validated('name'),
            $request->validated('roles'),
            $request->validated('permissions'),
        );

        return (new StaffMemberResource($member))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function access(StaffAccessRequest $request, string $id): StaffMemberResource
    {
        $member = $this->staff->updateAccess(
            $request->user(),
            $id,
            $request->validated('roles'),
            $request->validated('permissions'),
        );

        return new StaffMemberResource($member);
    }

    public function deactivate(Request $request, string $id): StaffMemberResource
    {
        $member = $this->staff->deactivate($request->user(), $id);

        return new StaffMemberResource($member);
    }

    public function reactivate(Request $request, string $id): StaffMemberResource
    {
        $member = $this->staff->reactivate($request->user(), $id);

        return new StaffMemberResource($member);
    }
}

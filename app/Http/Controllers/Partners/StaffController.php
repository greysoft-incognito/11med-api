<?php

namespace App\Http\Controllers\Partners;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Partners\InviteStaffRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Partners\StaffMemberCollection;
use App\Http\Resources\Partners\StaffMemberResource;
use App\Services\Partners\PartnerStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A pharmacy's or laboratory's staff, managed by its owner.
 */
class StaffController extends Controller
{
    public function __construct(
        private readonly PartnerStaff $staff,
    ) {}

    public function index(Request $request): StaffMemberCollection
    {
        $page = $this->staff->list($request->user(), ListQuery::from($request));

        return new StaffMemberCollection($page);
    }

    public function store(InviteStaffRequest $request): JsonResponse
    {
        $member = $this->staff->invite($request->user(), $request->validated());

        return (new StaffMemberResource($member))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function destroy(Request $request, string $id): EmptyResource
    {
        $this->staff->remove($request->user(), $id);

        return new EmptyResource;
    }

    public function makeOwner(Request $request, string $id): StaffMemberResource
    {
        $member = $this->staff->makeOwner($request->user(), $id);

        return new StaffMemberResource($member);
    }
}

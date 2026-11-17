<?php

namespace App\Http\Controllers\Caregiving;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Caregiving\AcceptInviteRequest;
use App\Http\Requests\Caregiving\InviteCaregiverRequest;
use App\Http\Resources\Caregiving\CaredForPatientCollection;
use App\Http\Resources\Caregiving\CaredForPatientResource;
use App\Http\Resources\Caregiving\CaregiverCollection;
use App\Http\Resources\Caregiving\CaregiverResource;
use App\Http\Resources\EmptyResource;
use App\Services\Caregiving\Caregivers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaregiverController extends Controller
{
    public function __construct(
        private readonly Caregivers $caregivers,
    ) {}

    public function index(Request $request): CaregiverCollection
    {
        $page = $this->caregivers->of($request->user(), ListQuery::from($request));

        return new CaregiverCollection($page);
    }

    public function invite(InviteCaregiverRequest $request): JsonResponse
    {
        $caregiver = $this->caregivers->invite($request->user(), $request->validated('phone'));

        return (new CaregiverResource($caregiver))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function accept(AcceptInviteRequest $request): CaredForPatientResource
    {
        $caregiver = $this->caregivers->accept($request->user(), $request->validated('code'));

        return new CaredForPatientResource($caregiver);
    }

    public function destroy(Request $request, string $caregiverId): EmptyResource
    {
        $this->caregivers->revoke($request->user(), $caregiverId);

        return new EmptyResource;
    }

    public function caringFor(Request $request): CaredForPatientCollection
    {
        $page = $this->caregivers->caringFor($request->user(), ListQuery::from($request));

        return new CaredForPatientCollection($page);
    }
}

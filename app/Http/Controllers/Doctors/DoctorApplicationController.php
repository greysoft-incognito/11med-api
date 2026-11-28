<?php

namespace App\Http\Controllers\Doctors;

use App\Enums\DocumentKind;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Doctors\ReviewApplicationRequest;
use App\Http\Requests\Doctors\ReviewDocumentRequest;
use App\Http\Resources\Doctors\DoctorApplicationCollection;
use App\Http\Resources\Doctors\DoctorApplicationResource;
use App\Services\Doctors\DoctorApplications;
use Illuminate\Http\Request;

class DoctorApplicationController extends Controller
{
    public function __construct(
        private readonly DoctorApplications $applications,
    ) {}

    public function index(Request $request): DoctorApplicationCollection
    {
        $status = VerificationStatus::tryFrom($request->string('status')->toString());
        $page = $this->applications->list(ListQuery::from($request), $status, $request->string('q')->toString());

        return new DoctorApplicationCollection($page);
    }

    public function show(string $id): DoctorApplicationResource
    {
        $profile = $this->applications->find($id);

        return new DoctorApplicationResource($profile);
    }

    public function reviewDocument(ReviewDocumentRequest $request, string $id): DoctorApplicationResource
    {
        $kind = DocumentKind::from($request->validated('kind'));
        $accepted = $request->validated('decision') === 'accepted';
        $profile = $this->applications->reviewDocument($id, $kind, $accepted, $request->validated('reason'), $request->user());

        return new DoctorApplicationResource($profile);
    }

    public function review(ReviewApplicationRequest $request, string $id): DoctorApplicationResource
    {
        $profile = $this->applications->decide($id, $request->validated('decision'), $request->validated('notes'), $request->user());

        return new DoctorApplicationResource($profile);
    }
}

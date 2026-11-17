<?php

namespace App\Http\Controllers\Consultations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultations\IssueLabRequestRequest;
use App\Http\Resources\Laboratories\LabRequestResource;
use App\Services\Consultations\ConsultationRecords;
use App\Services\Consultations\LabRequesting;
use Illuminate\Http\Request;

/**
 * The doctor's request for tests from a session (app spec §11.4).
 */
class LabRequestController extends Controller
{
    public function __construct(
        private readonly ConsultationRecords $records,
        private readonly LabRequesting $labRequests,
    ) {}

    public function show(Request $request, string $id): LabRequestResource
    {
        $consultation = $this->records->ofDoctor($request->user(), $id);
        $labRequest = $this->labRequests->of($consultation);

        return new LabRequestResource($labRequest);
    }

    public function issue(IssueLabRequestRequest $request, string $id): LabRequestResource
    {
        $consultation = $this->records->ofDoctor($request->user(), $id);
        $labRequest = $this->labRequests->issue($consultation, $request->user(), $request->validated());

        return new LabRequestResource($labRequest);
    }
}

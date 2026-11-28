<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Operations\CancelByTeamRequest;
use App\Http\Requests\Operations\ListConsultationsRequest;
use App\Http\Resources\Operations\AdminConsultationCollection;
use App\Http\Resources\Operations\AdminConsultationResource;
use App\Services\Consultations\ConsultationDesk;

class AdminConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationDesk $desk,
    ) {}

    public function index(ListConsultationsRequest $request): AdminConsultationCollection
    {
        $page = $this->desk->list($request->validated('status'), $request->validated('q'), ListQuery::from($request));

        return new AdminConsultationCollection($page);
    }

    public function show(string $id): AdminConsultationResource
    {
        $consultation = $this->desk->find($id);

        return new AdminConsultationResource($consultation);
    }

    public function cancel(CancelByTeamRequest $request, string $id): AdminConsultationResource
    {
        $consultation = $this->desk->cancel($request->user(), $id, $request->validated('reason'));

        return new AdminConsultationResource($consultation);
    }
}

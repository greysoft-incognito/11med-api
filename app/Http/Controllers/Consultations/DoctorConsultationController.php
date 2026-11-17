<?php

namespace App\Http\Controllers\Consultations;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Resources\Consultations\CallSessionResource;
use App\Http\Resources\Consultations\ConsultationResource;
use App\Http\Resources\Consultations\DoctorConsultationCollection;
use App\Http\Resources\Consultations\EarningsResource;
use App\Services\Consultations\CallRooms;
use App\Services\Consultations\ConsultationLifecycle;
use App\Services\Consultations\ConsultationRecords;
use App\Services\Consultations\Earnings;
use Illuminate\Http\Request;

/**
 * The doctor's side (app spec §7.2, §7.7): the inbox of paid requests, moving one, the call, earnings.
 */
class DoctorConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationRecords $records,
        private readonly ConsultationLifecycle $lifecycle,
    ) {}

    public function index(Request $request): DoctorConsultationCollection
    {
        $page = $this->records->inbox($request->user(), ListQuery::from($request));

        return new DoctorConsultationCollection($page);
    }

    public function accept(Request $request, string $id): ConsultationResource
    {
        $consultation = $this->lifecycle->accept($this->records->ofDoctor($request->user(), $id));

        return new ConsultationResource($consultation);
    }

    public function decline(Request $request, string $id): ConsultationResource
    {
        $consultation = $this->lifecycle->decline($this->records->ofDoctor($request->user(), $id));

        return new ConsultationResource($consultation);
    }

    public function start(Request $request, string $id): ConsultationResource
    {
        $consultation = $this->lifecycle->start($this->records->ofDoctor($request->user(), $id));

        return new ConsultationResource($consultation);
    }

    public function end(Request $request, string $id): ConsultationResource
    {
        $consultation = $this->lifecycle->end($this->records->ofDoctor($request->user(), $id));

        return new ConsultationResource($consultation);
    }

    public function call(Request $request, string $id, CallRooms $rooms): CallSessionResource
    {
        $call = $rooms->join($this->records->ofDoctor($request->user(), $id), $request->user(), true);

        return new CallSessionResource($call);
    }

    public function earnings(Request $request, Earnings $earnings): EarningsResource
    {
        $summary = $earnings->of($request->user());

        return new EarningsResource($summary);
    }
}

<?php

namespace App\Http\Controllers\Consultations;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Consultations\BookConsultationRequest;
use App\Http\Requests\Consultations\RateConsultationRequest;
use App\Http\Resources\Consultations\CallSessionResource;
use App\Http\Resources\Consultations\ConsultationCollection;
use App\Http\Resources\Consultations\ConsultationResource;
use App\Http\Resources\EmptyResource;
use App\Services\Consultations\Bookings;
use App\Services\Consultations\CallRooms;
use App\Services\Consultations\ConsultationLifecycle;
use App\Services\Consultations\ConsultationRecords;
use App\Services\Consultations\Ratings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The patient's consultations (app spec §7.2, §7.8).
 */
class ConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationRecords $records,
    ) {}

    public function index(Request $request): ConsultationCollection
    {
        $page = $this->records->forPatient($request->user(), ListQuery::from($request));

        return new ConsultationCollection($page);
    }

    public function store(BookConsultationRequest $request, Bookings $bookings): JsonResponse
    {
        $consultation = $bookings->book($request->user(), $request->validated());

        return (new ConsultationResource($consultation))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Request $request, string $id): ConsultationResource
    {
        $consultation = $this->records->ofPatient($request->user(), $id);

        return new ConsultationResource($consultation);
    }

    public function cancel(Request $request, string $id, ConsultationLifecycle $lifecycle): ConsultationResource
    {
        $consultation = $lifecycle->cancel($this->records->ofPatient($request->user(), $id));

        return new ConsultationResource($consultation);
    }

    public function call(Request $request, string $id, CallRooms $rooms): CallSessionResource
    {
        $call = $rooms->join($this->records->ofPatient($request->user(), $id), $request->user(), false);

        return new CallSessionResource($call);
    }

    public function rate(RateConsultationRequest $request, string $id, Ratings $ratings): EmptyResource
    {
        $ratings->rate($this->records->ofPatient($request->user(), $id), $request->integer('rating'), $request->validated('review'));

        return new EmptyResource;
    }
}

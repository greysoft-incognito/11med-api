<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Operations\CancelByTeamRequest;
use App\Http\Requests\Operations\ListLabBookingsRequest;
use App\Http\Resources\Operations\AdminLabBookingCollection;
use App\Http\Resources\Operations\AdminLabBookingResource;
use App\Services\Laboratories\BookingDesk;

class AdminLabBookingController extends Controller
{
    public function __construct(
        private readonly BookingDesk $desk,
    ) {}

    public function index(ListLabBookingsRequest $request): AdminLabBookingCollection
    {
        $page = $this->desk->list($request->validated('status'), $request->validated('q'), ListQuery::from($request));

        return new AdminLabBookingCollection($page);
    }

    public function show(string $id): AdminLabBookingResource
    {
        $booking = $this->desk->find($id);

        return new AdminLabBookingResource($booking);
    }

    public function cancel(CancelByTeamRequest $request, string $id): AdminLabBookingResource
    {
        $booking = $this->desk->cancel($request->user(), $id, $request->validated('reason'));

        return new AdminLabBookingResource($booking);
    }
}

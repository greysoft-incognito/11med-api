<?php

namespace App\Http\Controllers\Laboratories;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Laboratories\BookLabRequest;
use App\Http\Resources\Laboratories\LabBookingCollection;
use App\Http\Resources\Laboratories\LabBookingResource;
use App\Services\Laboratories\LabBookings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The patient's laboratory bookings.
 */
class LabBookingController extends Controller
{
    public function __construct(
        private readonly LabBookings $bookings,
    ) {}

    public function index(Request $request): LabBookingCollection
    {
        $page = $this->bookings->of($request->user(), ListQuery::from($request));

        return new LabBookingCollection($page);
    }

    public function show(Request $request, string $bookingId): LabBookingResource
    {
        $booking = $this->bookings->find($request->user(), $bookingId);

        return new LabBookingResource($booking);
    }

    public function store(BookLabRequest $request): JsonResponse
    {
        $booking = $this->bookings->book($request->user(), $request->validated());

        return (new LabBookingResource($booking))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function cancel(Request $request, string $bookingId): LabBookingResource
    {
        $booking = $this->bookings->cancel($request->user(), $bookingId);

        return new LabBookingResource($booking);
    }
}

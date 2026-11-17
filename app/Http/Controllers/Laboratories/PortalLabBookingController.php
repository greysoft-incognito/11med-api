<?php

namespace App\Http\Controllers\Laboratories;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Laboratories\AdvanceBookingRequest;
use App\Http\Requests\Laboratories\CancelLabBookingRequest;
use App\Http\Requests\Laboratories\LaboratoryBookingListRequest;
use App\Http\Resources\Laboratories\LaboratoryBookingCollection;
use App\Http\Resources\Laboratories\LaboratoryBookingResource;
use App\Services\Laboratories\Laboratories;
use App\Services\Laboratories\LaboratoryBookings;
use Illuminate\Http\Request;

/**
 * The laboratory portal's bookings: paid ones only, moved to results or cancelled.
 */
class PortalLabBookingController extends Controller
{
    public function __construct(
        private readonly Laboratories $laboratories,
        private readonly LaboratoryBookings $bookings,
    ) {}

    public function index(LaboratoryBookingListRequest $request): LaboratoryBookingCollection
    {
        $laboratory = $this->laboratories->of($request->user());

        $page = $this->bookings->of(
            $laboratory,
            $request->validated('status'),
            $request->validated('q'),
            ListQuery::from($request)
        );

        return new LaboratoryBookingCollection($page);
    }

    public function show(Request $request, string $id): LaboratoryBookingResource
    {
        $laboratory = $this->laboratories->of($request->user());
        $booking = $this->bookings->find($laboratory, $id);

        return new LaboratoryBookingResource($booking);
    }

    public function advance(AdvanceBookingRequest $request, string $id): LaboratoryBookingResource
    {
        $laboratory = $this->laboratories->of($request->user());
        $booking = $this->bookings->advance($request->user(), $laboratory, $id, $request->validated());

        return new LaboratoryBookingResource($booking);
    }

    public function cancel(CancelLabBookingRequest $request, string $id): LaboratoryBookingResource
    {
        $laboratory = $this->laboratories->of($request->user());
        $booking = $this->bookings->cancel($laboratory, $id, $request->validated('reason'));

        return new LaboratoryBookingResource($booking);
    }
}

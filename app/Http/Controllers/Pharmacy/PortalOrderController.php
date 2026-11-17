<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Pharmacy\AdvanceOrderRequest;
use App\Http\Requests\Pharmacy\CancelOrderRequest;
use App\Http\Resources\Pharmacy\PharmacyOrderCollection;
use App\Http\Resources\Pharmacy\PharmacyOrderResource;
use App\Services\Pharmacy\OrderBook;
use App\Services\Pharmacy\PharmacyStore;
use Illuminate\Http\Request;

class PortalOrderController extends Controller
{
    public function __construct(
        private readonly PharmacyStore $store,
        private readonly OrderBook $orders,
    ) {}

    public function index(Request $request): PharmacyOrderCollection
    {
        $pharmacy = $this->store->of($request->user());
        $page = $this->orders->list($pharmacy, $request->only(['status', 'q']), ListQuery::from($request));

        return new PharmacyOrderCollection($page);
    }

    public function show(Request $request, string $id): PharmacyOrderResource
    {
        $pharmacy = $this->store->of($request->user());
        $order = $this->orders->find($pharmacy, $id);

        return new PharmacyOrderResource($order);
    }

    public function advance(AdvanceOrderRequest $request, string $id): PharmacyOrderResource
    {
        $pharmacy = $this->store->of($request->user());
        $order = $this->orders->advance($pharmacy, $id, $request->validated());

        return new PharmacyOrderResource($order);
    }

    public function cancel(CancelOrderRequest $request, string $id): PharmacyOrderResource
    {
        $pharmacy = $this->store->of($request->user());
        $order = $this->orders->cancel($pharmacy, $id, $request->validated('reason'));

        return new PharmacyOrderResource($order);
    }
}

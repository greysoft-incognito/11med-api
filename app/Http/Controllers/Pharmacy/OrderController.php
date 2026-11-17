<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Pharmacy\PlaceOrderRequest;
use App\Http\Resources\Pharmacy\OrderCollection;
use App\Http\Resources\Pharmacy\OrderResource;
use App\Services\Pharmacy\Checkout;
use App\Services\Pharmacy\PatientOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly PatientOrders $orders,
        private readonly Checkout $checkout,
    ) {}

    public function index(Request $request): OrderCollection
    {
        $page = $this->orders->list($request->user(), ListQuery::from($request));

        return new OrderCollection($page);
    }

    public function show(Request $request, string $orderId): OrderResource
    {
        $order = $this->orders->find($request->user(), $orderId);

        return new OrderResource($order);
    }

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $orders = $this->checkout->place($request->user(), $request->validated());

        return (new OrderCollection($orders))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function cancel(Request $request, string $orderId): OrderResource
    {
        $order = $this->orders->cancel($request->user(), $orderId);

        return new OrderResource($order);
    }
}

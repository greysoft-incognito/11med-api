<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Operations\CancelByTeamRequest;
use App\Http\Requests\Operations\ListOrdersRequest;
use App\Http\Resources\Operations\AdminOrderCollection;
use App\Http\Resources\Operations\AdminOrderResource;
use App\Services\Pharmacy\OrderDesk;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderDesk $desk,
    ) {}

    public function index(ListOrdersRequest $request): AdminOrderCollection
    {
        $page = $this->desk->list($request->validated('status'), $request->validated('q'), ListQuery::from($request));

        return new AdminOrderCollection($page);
    }

    public function show(string $id): AdminOrderResource
    {
        $order = $this->desk->find($id);

        return new AdminOrderResource($order);
    }

    public function cancel(CancelByTeamRequest $request, string $id): AdminOrderResource
    {
        $order = $this->desk->cancel($request->user(), $id, $request->validated('reason'));

        return new AdminOrderResource($order);
    }
}

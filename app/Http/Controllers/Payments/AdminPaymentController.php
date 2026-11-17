<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Payments\ListAdminPaymentsRequest;
use App\Http\Resources\Payments\AdminPaymentCollection;
use App\Services\Payments\PaymentDesk;

class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentDesk $desk,
    ) {}

    public function index(ListAdminPaymentsRequest $request): AdminPaymentCollection
    {
        $page = $this->desk->list(ListQuery::from($request), $request->validated('status'), $request->validated('q'));

        return new AdminPaymentCollection($page);
    }
}

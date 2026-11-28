<?php

namespace App\Http\Controllers\Consultations;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Consultations\SettlePayoutRequest;
use App\Http\Resources\Consultations\AdminPayoutCollection;
use App\Http\Resources\Consultations\AdminPayoutResource;
use App\Services\Consultations\Payouts;
use Illuminate\Http\Request;

/**
 * The team's payout desk (portal handover §6 "Payouts").
 */
class PayoutDeskController extends Controller
{
    public function __construct(
        private readonly Payouts $payouts,
    ) {}

    public function index(Request $request): AdminPayoutCollection
    {
        $status = PayoutStatus::tryFrom($request->string('status')->toString());
        $page = $this->payouts->list($status, ListQuery::from($request));

        return new AdminPayoutCollection($page);
    }

    public function settle(SettlePayoutRequest $request, string $id): AdminPayoutResource
    {
        $payout = $this->payouts->settle($id, $request->validated(), $request->user());

        return new AdminPayoutResource($payout);
    }
}

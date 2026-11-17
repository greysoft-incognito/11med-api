<?php

namespace App\Http\Controllers\Subscriptions;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Subscriptions\RecordSubscriptionRequest;
use App\Http\Resources\Subscriptions\SubscriptionRecordCollection;
use App\Http\Resources\Subscriptions\SubscriptionRecordResource;
use App\Services\Subscriptions\Subscriptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
    ) {}

    public function index(Request $request): SubscriptionRecordCollection
    {
        $q = trim($request->string('q')->toString());
        $page = $this->subscriptions->records($q, ListQuery::from($request));

        return new SubscriptionRecordCollection($page);
    }

    /**
     * 200 whether the record is new or replaced: recording is an upsert.
     */
    public function store(RecordSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->subscriptions->record(
            $request->user(),
            $request->validated('phone'),
            $request->validated('premium_until'),
            $request->validated('reference'),
        );

        return (new SubscriptionRecordResource($subscription))
            ->response()
            ->setStatusCode(HttpStatus::OK->value);
    }
}

<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Resources\Subscriptions\SubscriptionResource;
use App\Services\Subscriptions\Subscriptions;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
    ) {}

    public function show(Request $request, string $userId): SubscriptionResource
    {
        $state = $this->subscriptions->stateFor($request->user(), $userId);

        return new SubscriptionResource($state);
    }
}

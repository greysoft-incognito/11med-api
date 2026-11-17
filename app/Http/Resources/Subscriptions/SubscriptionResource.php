<?php

namespace App\Http\Resources\Subscriptions;

use App\Http\Resources\ApiResource;
use App\Services\Subscriptions\SubscriptionState;
use Illuminate\Http\Request;

/**
 * `zSubscription`, the app's read of its own plan.
 *
 * @property SubscriptionState $resource
 */
class SubscriptionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->status->value,
            'plan_id' => $this->resource->plan?->value,
            'current_period_end' => $this->iso($this->resource->periodEnd),
            'cancels_at_period_end' => $this->resource->cancelsAtPeriodEnd,
        ];
    }
}

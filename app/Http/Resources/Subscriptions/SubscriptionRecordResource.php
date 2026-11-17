<?php

namespace App\Http\Resources\Subscriptions;

use App\Enums\Plan;
use App\Http\Resources\ApiResource;
use App\Models\Subscription;
use Illuminate\Http\Request;

/**
 * `zSubscriptionRecord`, as the team sees it. A lapsed record reads as free with its last end date.
 *
 * @property Subscription $resource
 */
class SubscriptionRecordResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->resource->isActive() ? Plan::Premium : Plan::Free;

        return [
            'user_id' => $this->resource->user->uuid,
            'patient_name' => $this->resource->user->full_name,
            'phone' => $this->resource->user->phone,
            'plan' => $plan->value,
            'premium_until' => $this->day($this->resource->premium_until),
            'reference' => $this->resource->reference,
            'updated_at' => $this->iso($this->resource->updated_at),
        ];
    }
}

<?php

namespace App\Services\Subscriptions;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * What the app reads as `zSubscription`. A recorded premium does not renew, so an active one
 * always ends at its period end.
 */
final readonly class SubscriptionState
{
    public function __construct(
        public SubscriptionStatus $status,
        public ?Plan $plan,
        public ?CarbonImmutable $periodEnd,
        public bool $cancelsAtPeriodEnd,
    ) {}

    public static function of(?Subscription $subscription): self
    {
        if ($subscription === null || ! $subscription->isActive()) {
            return new self(SubscriptionStatus::None, null, null, false);
        }

        return new self(SubscriptionStatus::Active, Plan::Premium, $subscription->endsAt(), true);
    }
}

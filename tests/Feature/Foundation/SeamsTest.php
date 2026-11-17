<?php

namespace Tests\Feature\Foundation;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Notifications\DeliveringNotifier;
use App\Services\Notifications\Notifier;
use App\Services\Payments\GatewayPayments;
use App\Services\Payments\Payments;
use App\Services\Subscriptions\Entitlements;
use Tests\TestCase;

class SeamsTest extends TestCase
{
    public function test_each_seam_resolves_to_its_owner(): void
    {
        $this->assertInstanceOf(DeliveringNotifier::class, app(Notifier::class));
        $this->assertInstanceOf(GatewayPayments::class, app(Payments::class));
    }

    public function test_a_free_account_gets_the_free_tier(): void
    {
        $user = User::factory()->create();
        $entitlements = app(Entitlements::class);

        $this->assertSame(3, $entitlements->medicationLimit($user));
        $this->assertSame(7, $entitlements->historyDays($user));
        $this->assertSame(7, $entitlements->readingsDays($user));
        $this->assertSame(1, $entitlements->caregiverLimit($user));
    }

    public function test_a_premium_feature_is_refused_on_the_free_tier(): void
    {
        $user = User::factory()->create();

        try {
            app(Entitlements::class)->requirePremium($user);
            $this->fail('A free account reached a premium feature.');
        } catch (ApiException $e) {
            $this->assertSame('PREMIUM_REQUIRED', $e->errorCode);
            $this->assertSame(403, $e->status->value);
        }
    }
}

<?php

namespace Tests\Feature\Foundation;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Medications\NoPrescribedSchedules;
use App\Services\Medications\PrescribedSchedules;
use App\Services\Notifications\DeliveringNotifier;
use App\Services\Notifications\Notifier;
use App\Services\Payments\NoPayments;
use App\Services\Payments\Payments;
use App\Services\Subscriptions\Entitlements;
use Tests\TestCase;

class SeamsTest extends TestCase
{
    public function test_each_seam_resolves_to_its_stand_in(): void
    {
        $this->assertInstanceOf(DeliveringNotifier::class, app(Notifier::class));
        $this->assertInstanceOf(NoPayments::class, app(Payments::class));
        $this->assertInstanceOf(NoPrescribedSchedules::class, app(PrescribedSchedules::class));
    }

    public function test_the_stand_ins_do_nothing(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], app(PrescribedSchedules::class)->apply($user, $user, 'prescription', []));
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

<?php

namespace Tests\Unit;

use App\Enums\AccountKind;
use App\Exceptions\ApiException;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscriptions\Entitlements;
use Tests\TestCase;

class EntitlementsTest extends TestCase
{
    private Entitlements $entitlements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17T23:30:00Z');
        $this->entitlements = app(Entitlements::class);
    }

    public function test_the_free_tier(): void
    {
        $user = $this->patient();

        $this->assertSame(3, $this->entitlements->medicationLimit($user));
        $this->assertSame(1, $this->entitlements->caregiverLimit($user));
        $this->assertSame(7, $this->entitlements->historyDays($user));
        $this->assertSame(7, $this->entitlements->readingsDays($user));
        $this->assertSame('2026-11-12', $this->entitlements->historyFrom($user)->toDateString());
        $this->assertSame('2026-11-12', $this->entitlements->readingsFrom($user)->toDateString());
    }

    public function test_premium_lifts_every_gate(): void
    {
        $user = $this->patient(premium: true);

        $this->assertNull($this->entitlements->medicationLimit($user));
        $this->assertSame(5, $this->entitlements->caregiverLimit($user));
        $this->assertNull($this->entitlements->historyDays($user));
        $this->assertNull($this->entitlements->readingsFrom($user));

        $this->entitlements->requirePremium($user);
        $this->entitlements->ensureCanAddMedication($user, 40);
        $this->addToAssertionCount(2);
    }

    public function test_the_medication_cap_exempts_prescriptions(): void
    {
        $user = $this->patient();

        $this->entitlements->ensureCanAddMedication($user, 2);
        $this->entitlements->ensureCanAddMedication($user, 3, prescribed: true);
        $this->addToAssertionCount(2);

        try {
            $this->entitlements->ensureCanAddMedication($user, 3);
            $this->fail('The fourth medication was allowed.');
        } catch (ApiException $e) {
            $this->assertSame('MEDICATION_LIMIT_REACHED', $e->errorCode);
            $this->assertSame(422, $e->status->value);
            $this->assertSame(['limit' => 3, 'active' => 3], $e->meta);
        }
    }

    public function test_premium_features_are_refused_on_the_free_tier(): void
    {
        try {
            $this->entitlements->requirePremium($this->patient());
            $this->fail('A free account reached a premium feature.');
        } catch (ApiException $e) {
            $this->assertSame('PREMIUM_REQUIRED', $e->errorCode);
            $this->assertSame(403, $e->status->value);
        }
    }

    private function patient(bool $premium = false): User
    {
        $user = User::factory()
            ->app(AccountKind::Patient)
            ->create();

        if ($premium) {
            Subscription::create(['user_id' => $user->id, 'premium_until' => '2026-12-31', 'reference' => 'PSK-0001']);
        }

        return $user->fresh();
    }
}

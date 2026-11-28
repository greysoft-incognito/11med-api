<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\AccountKind;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Identity\AccountEraser;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_a_patient_without_a_record_reads_none(): void
    {
        $patient = $this->account(AccountKind::Patient);

        $this->actingAs($patient)
            ->getJson("api/v1/subscriptions/{$patient->uuid}")
            ->assertOk()
            ->assertJsonPath('data', [
                'status' => 'none',
                'plan_id' => null,
                'current_period_end' => null,
                'cancels_at_period_end' => false,
            ]);
    }

    public function test_recorded_premium_is_active_until_the_end_of_its_day_in_lagos(): void
    {
        $this->travelTo('2026-11-17T12:00:00Z');

        $patient = $this->account(AccountKind::Patient);
        $this->premium($patient, '2026-12-31');

        $this->actingAs($patient)
            ->getJson("api/v1/subscriptions/{$patient->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan_id', 'premium')
            ->assertJsonPath('data.current_period_end', '2026-12-31T23:00:00.000Z')
            ->assertJsonPath('data.cancels_at_period_end', true);

        $this->assertTrue($patient->fresh()->isPremium());

        $this->travelTo('2026-12-31T22:59:00Z');
        $this->assertTrue($patient->fresh()->isPremium());

        $this->travelTo('2026-12-31T23:00:00Z');
        $this->assertFalse($patient->fresh()->isPremium());

        $this->actingAs($patient->fresh())
            ->getJson("api/v1/subscriptions/{$patient->uuid}")
            ->assertJsonPath('data.status', 'none')
            ->assertJsonPath('data.current_period_end', null);
    }

    public function test_the_session_carries_is_premium_from_the_record(): void
    {
        $patient = $this->account(AccountKind::Patient);
        $this->premium($patient, now()->addMonth()->toDateString());

        $this->postJson('api/v1/auth/login', ['phone_number' => $patient->phone, 'password' => 'Password1', 'device_id' => '0b8f3a52-3c55-4c9e-9a3e-2f3c1d4e5f60'])
            ->assertOk()
            ->assertJsonPath('data.user.is_premium', true);
    }

    public function test_a_doctor_reads_their_own_and_nobody_reads_another(): void
    {
        $doctor = $this->account(AccountKind::Doctor);
        $patient = $this->account(AccountKind::Patient);

        $this->actingAs($doctor)
            ->getJson("api/v1/subscriptions/{$doctor->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'none');

        $this->actingAs($doctor)
            ->getJson("api/v1/subscriptions/{$patient->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_portal_accounts_and_guests_are_refused(): void
    {
        $patient = $this->account(AccountKind::Patient);
        $admin = User::factory()
            ->admin()
            ->create();

        $this->getJson("api/v1/subscriptions/{$patient->uuid}")->assertUnauthorized();

        $this->actingAs($admin)
            ->getJson("api/v1/subscriptions/{$patient->uuid}")
            ->assertForbidden();
    }

    public function test_erasing_the_patient_removes_the_record_and_keeps_the_reference_used(): void
    {
        $patient = $this->account(AccountKind::Patient);
        $this->premium($patient, now()->addMonth()->toDateString());

        app(AccountEraser::class)->erase($patient, 'Password1', null);

        $this->assertSame(0, Subscription::withTrashed()->count());
        $this->assertNull(SubscriptionPayment::firstWhere('reference', 'PSK-0001')->user_id);
    }

    private function account(AccountKind $role): User
    {
        return User::factory()
            ->app($role)
            ->create();
    }

    private function premium(User $patient, string $until): void
    {
        Subscription::create(['user_id' => $patient->id, 'premium_until' => $until, 'reference' => 'PSK-0001']);
        SubscriptionPayment::create(['user_id' => $patient->id, 'reference' => 'PSK-0001', 'premium_until' => $until, 'recorded_at' => now()]);
    }
}

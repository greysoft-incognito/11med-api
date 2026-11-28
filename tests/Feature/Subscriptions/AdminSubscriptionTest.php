<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\AccountKind;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Tests\TestCase;

class AdminSubscriptionTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17T12:00:00Z');

        $this->admin = User::factory()
            ->admin()
            ->create();
    }

    public function test_recording_makes_the_patient_premium_and_keeps_the_payment(): void
    {
        $patient = $this->patient('08031112222', 'Ada Obi');

        $this->actingAs($this->admin)
            ->postJson('api/v1/portal/admin/subscriptions', [
                'phone' => '+2348031112222',
                'premium_until' => '2027-11-17',
                'reference' => 'PSK-20261117-01',
            ])
            ->assertOk()
            ->assertJsonPath('data', [
                'user_id' => $patient->uuid,
                'patient_name' => 'Ada Obi',
                'phone' => '08031112222',
                'plan' => 'premium',
                'premium_until' => '2027-11-17',
                'reference' => 'PSK-20261117-01',
                'updated_at' => '2026-11-17T12:00:00.000Z',
            ]);

        $this->assertTrue($patient->fresh()->isPremium());
        $this->assertSame($this->admin->id, SubscriptionPayment::firstWhere('reference', 'PSK-20261117-01')->recorded_by);
    }

    public function test_recording_again_extends_the_same_record(): void
    {
        $patient = $this->patient('08031112222');

        $this->record('08031112222', '2026-12-17', 'PSK-0001')->assertOk();
        $this->record('08031112222', '2027-12-17', 'PSK-0002')
            ->assertOk()
            ->assertJsonPath('data.premium_until', '2027-12-17')
            ->assertJsonPath('data.reference', 'PSK-0002');

        $this->assertSame(1, Subscription::query()->where('user_id', $patient->id)->count());
        $this->assertSame(2, SubscriptionPayment::query()->where('user_id', $patient->id)->count());
    }

    public function test_a_reference_is_recorded_once(): void
    {
        $this->patient('08031112222');
        $this->patient('08031113333');

        $this->record('08031112222', '2026-12-17', 'PSK-0001')->assertOk();

        $this->record('08031113333', '2026-12-17', 'PSK-0001')
            ->assertConflict()
            ->assertJsonPath('error.code', 'REFERENCE_USED')
            ->assertJsonPath('error.field', 'reference');
    }

    public function test_only_a_verified_patient_number_is_found(): void
    {
        User::factory()
            ->app(AccountKind::Doctor)
            ->create(['phone' => '08031114444']);
        User::factory()
            ->app(AccountKind::Patient)
            ->create(['phone' => '08031115555', 'phone_verified_at' => null]);

        foreach (['08031110000', '08031114444', '08031115555'] as $phone) {
            $this->record($phone, '2026-12-17', 'PSK-' . $phone)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'PATIENT_NOT_FOUND')
                ->assertJsonPath('error.field', 'phone');
        }
    }

    public function test_the_end_date_must_be_after_today_in_lagos(): void
    {
        $this->patient('08031112222');

        $this->travelTo('2026-11-17T23:30:00Z');

        $this->record('08031112222', '2026-11-18', 'PSK-0001')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'premium_until');

        $this->record('08031112222', '2026-11-19', 'PSK-0001')->assertOk();
    }

    public function test_the_body_is_validated(): void
    {
        $this->actingAs($this->admin)
            ->postJson('api/v1/portal/admin/subscriptions', ['phone' => '123', 'premium_until' => '2027-01-01', 'reference' => 'PSK-1'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'phone');

        $this->actingAs($this->admin)
            ->postJson('api/v1/portal/admin/subscriptions', ['phone' => '08031112222', 'premium_until' => '2027-01-01', 'reference' => 'abc'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'reference');
    }

    public function test_the_list_is_newest_first_paged_and_searchable(): void
    {
        $this->patient('08031112222', 'Ada Obi');
        $this->patient('08031113333', 'Chidi Eze');

        $this->record('08031112222', '2026-12-17', 'PSK-AAAA')->assertOk();
        $this->travel(1)->minutes();
        $this->record('08031113333', '2026-12-17', 'PSK-BBBB')->assertOk();

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/subscriptions?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.patient_name', 'Chidi Eze')
            ->assertJsonPath('data.next_cursor', fn($cursor) => is_string($cursor));

        foreach (['ada', '+2348031112222', '1112', 'aaaa'] as $q) {
            $this->actingAs($this->admin)
                ->getJson('api/v1/portal/admin/subscriptions?q=' . urlencode($q))
                ->assertJsonPath('data.total', 1)
                ->assertJsonPath('data.items.0.patient_name', 'Ada Obi');
        }

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/subscriptions?q=%25')
            ->assertJsonPath('data.total', 0);
    }

    public function test_a_lapsed_record_reads_free_with_its_last_end_date(): void
    {
        $this->patient('08031112222');
        $this->record('08031112222', '2026-12-17', 'PSK-0001')->assertOk();

        $this->travelTo('2026-12-18T12:00:00Z');

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/subscriptions')
            ->assertJsonPath('data.items.0.plan', 'free')
            ->assertJsonPath('data.items.0.premium_until', '2026-12-17');
    }

    public function test_other_roles_are_refused(): void
    {
        $pharmacy = User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create();
        $patient = $this->patient('08031112222');

        foreach ([$pharmacy, $patient] as $user) {
            $this->actingAs($user)
                ->getJson('api/v1/portal/admin/subscriptions')
                ->assertForbidden();

            $this->actingAs($user)
                ->postJson('api/v1/portal/admin/subscriptions', ['phone' => '08031112222', 'premium_until' => '2027-01-01', 'reference' => 'PSK-0001'])
                ->assertForbidden();
        }
    }

    private function patient(string $phone, ?string $name = null): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create(array_filter(['phone' => $phone, 'full_name' => $name]));
    }

    private function record(string $phone, string $until, string $reference)
    {
        return $this->actingAs($this->admin)
            ->postJson('api/v1/portal/admin/subscriptions', [
                'phone' => $phone,
                'premium_until' => $until,
                'reference' => $reference,
            ]);
    }
}

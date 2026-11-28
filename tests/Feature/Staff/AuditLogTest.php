<?php

namespace Tests\Feature\Staff;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Models\AuditEvent;
use App\Models\LabTest;
use App\Models\User;
use App\Services\Audit\AuditLog;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()
            ->admin()
            ->create(['full_name' => 'Ada Super']);
    }

    public function test_an_admin_write_is_recorded_and_read_newest_first(): void
    {
        $this->travelTo('2026-11-29T09:00:00Z');

        $this->actingAs($this->admin)
            ->postJson('api/v1/portal/admin/catalogue', [
                'code' => 'ZZ_TEST',
                'name' => 'Test panel',
                'category' => 'haematology',
                'sample' => 'blood',
                'description' => 'A panel used by the audit test.',
                'fasting_hours' => 0,
                'turnaround_hours' => 24,
            ])
            ->assertCreated();

        $this->travelTo('2026-11-30T09:00:00Z');

        $this->actingAs($this->admin)
            ->putJson('api/v1/portal/admin/catalogue/ZZ_TEST', [
                'code' => 'ZZ_TEST',
                'name' => 'Test panel, full',
                'category' => 'haematology',
                'sample' => 'blood',
                'description' => 'A panel used by the audit test.',
                'fasting_hours' => 0,
                'turnaround_hours' => 24,
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/audit')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.action', 'catalogue.test_updated')
            ->assertJsonPath('data.items.0.actor_id', $this->admin->uuid)
            ->assertJsonPath('data.items.0.actor_name', 'Ada Super')
            ->assertJsonPath('data.items.0.subject_type', 'lab_test')
            ->assertJsonPath('data.items.0.subject_id', LabTest::query()->where('code', 'ZZ_TEST')->value('uuid'))
            ->assertJsonPath('data.items.0.meta.changed', ['name'])
            ->assertJsonPath('data.items.0.created_at', '2026-11-30T09:00:00.000Z')
            ->assertJsonPath('data.items.1.action', 'catalogue.test_created');
    }

    public function test_the_log_filters_by_actor_action_subject_and_lagos_day(): void
    {
        $other = User::factory()->admin()->create();
        $log = app(AuditLog::class);

        $this->travelTo('2026-11-29T23:30:00Z');
        $log->record($this->admin, 'staff.access_changed', $other);
        $this->travelTo('2026-11-29T10:00:00Z');
        $log->record($other, 'staff.invited', $this->admin);

        $this->actingAs($this->admin)
            ->getJson("api/v1/portal/admin/audit?actor={$other->uuid}")
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.action', 'staff.invited');

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/audit?action=staff.access_changed&subject_type=user&subject_id=' . $other->uuid)
            ->assertJsonPath('data.total', 1);

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/audit?from=2026-11-30&to=2026-11-30')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.action', 'staff.access_changed');

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/audit?from=2026-11-30&to=2026-11-29')
            ->assertBadRequest()
            ->assertJsonPath('error.field', 'to');
    }

    public function test_an_event_is_never_changed_or_removed(): void
    {
        app(AuditLog::class)->record($this->admin, 'staff.invited', $this->admin);
        $event = AuditEvent::query()->firstOrFail();

        $this->expectException(LogicException::class);

        $event->update(['action' => 'nothing']);
    }

    public function test_the_log_needs_the_audit_permission(): void
    {
        $reader = User::factory()->staff([AdminPermission::AuditView])->create();
        $nobody = User::factory()->staff([AdminPermission::OrdersView])->create();

        $this->actingAs($reader)->getJson('api/v1/portal/admin/audit')->assertOk();
        $this->actingAs($nobody)->getJson('api/v1/portal/admin/audit')->assertForbidden();
        $this->actingAs(User::factory()->portal(AccountKind::Laboratory)->create())
            ->getJson('api/v1/portal/admin/audit')
            ->assertForbidden();
    }

    public function test_recording_a_subscription_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $patient = User::factory()->app()->create(['phone' => '08031112222', 'phone_verified_at' => now()]);

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/subscriptions', ['phone' => '08031112222', 'premium_until' => '2027-01-01', 'reference' => 'PSK-AUDIT-1'])
            ->assertOk();

        $event = AuditEvent::query()->sole();
        $this->assertSame('subscription.recorded', $event->action);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertEqualsCanonicalizing(['patient_id' => $patient->uuid, 'premium_until' => '2027-01-01', 'reference' => 'PSK-AUDIT-1'], $event->meta);
    }
}

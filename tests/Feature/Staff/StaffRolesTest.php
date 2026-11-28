<?php

namespace Tests\Feature\Staff;

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Staff\StaffCatalogue;
use Tests\TestCase;

class StaffRolesTest extends TestCase
{
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()
            ->portal(Role::Admin)
            ->create();
    }

    public function test_the_defaults_are_seeded_and_the_super_admin_holds_everything(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('api/v1/portal/admin/roles')
            ->assertOk();

        $this->assertSame(
            ['Super admin', 'Catalogue', 'Finance', 'Support', 'Verification'],
            array_column($response->json('data'), 'name'),
        );
        $this->assertTrue($response->json('data.0.protected'));
        $this->assertSame(AdminPermission::values(), $response->json('data.0.permissions'));
        $this->assertSame(['summary.view', 'doctors.view', 'doctors.review', 'partners.view', 'partners.review'], $response->json('data.4.permissions'));
    }

    public function test_syncing_again_keeps_edits_and_does_not_revive_a_deleted_default(): void
    {
        $finance = StaffRole::query()->where('key', 'finance')->firstOrFail();
        $finance->syncPermissions(['payments.view']);
        StaffRole::query()->where('key', 'catalogue')->firstOrFail()->delete();

        app(StaffCatalogue::class)->sync();

        $this->assertSame(['payments.view'], $finance->fresh()->permissions->pluck('name')->all());
        $this->assertFalse(StaffRole::query()->where('key', 'catalogue')->exists());
        $this->assertCount(count(AdminPermission::cases()), StaffRole::query()->where('key', 'super-admin')->firstOrFail()->permissions->filter(fn($p) => AdminPermission::tryFrom($p->name) !== null));
    }

    public function test_the_permission_catalogue_lists_every_permission_by_area(): void
    {
        $this->actingAs($this->superAdmin)
            ->getJson('api/v1/portal/admin/permissions')
            ->assertOk()
            ->assertJsonCount(count(AdminPermission::cases()), 'data')
            ->assertJsonPath('data.1', ['name' => 'doctors.view', 'area' => 'doctors']);
    }

    public function test_a_role_is_created_edited_and_audited(): void
    {
        $id = $this->actingAs($this->superAdmin)
            ->postJson('api/v1/portal/admin/roles', [
                'name' => 'Night desk',
                'description' => 'Reads orders overnight.',
                'permissions' => ['orders.view', 'bookings.view'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Night desk')
            ->assertJsonPath('data.protected', false)
            ->assertJsonPath('data.permissions', ['orders.view', 'bookings.view'])
            ->assertJsonPath('data.staff_count', 0)
            ->json('data.id');

        $this->actingAs($this->superAdmin)
            ->putJson("api/v1/portal/admin/roles/{$id}", [
                'name' => 'Overnight desk',
                'permissions' => ['orders.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Overnight desk')
            ->assertJsonPath('data.permissions', ['orders.view']);

        $this->assertSame(['role.created', 'role.updated'], AuditEvent::query()->orderBy('id')->pluck('action')->all());
        $this->assertSame(['added' => [], 'name' => 'Overnight desk', 'removed' => ['bookings.view']], collect(AuditEvent::query()->latest('id')->first()->meta)->sortKeys()->all());
    }

    public function test_a_role_name_is_unique_and_permissions_must_exist(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson('api/v1/portal/admin/roles', ['name' => 'finance', 'permissions' => []])
            ->assertConflict()
            ->assertJsonPath('error.code', 'ROLE_NAME_TAKEN')
            ->assertJsonPath('error.field', 'name');

        $this->actingAs($this->superAdmin)
            ->postJson('api/v1/portal/admin/roles', ['name' => 'Odd', 'permissions' => ['everything']])
            ->assertBadRequest()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'permissions.0');
    }

    public function test_the_super_admin_role_cannot_be_edited_or_deleted(): void
    {
        $id = StaffRole::query()->where('key', 'super-admin')->value('uuid');

        $this->actingAs($this->superAdmin)
            ->putJson("api/v1/portal/admin/roles/{$id}", ['name' => 'Boss', 'permissions' => []])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ROLE_PROTECTED');

        $this->actingAs($this->superAdmin)
            ->deleteJson("api/v1/portal/admin/roles/{$id}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ROLE_PROTECTED');
    }

    public function test_a_held_role_is_deleted_only_by_moving_its_holders(): void
    {
        $finance = StaffRole::query()->where('key', 'finance')->firstOrFail();
        $support = StaffRole::query()->where('key', 'support')->firstOrFail();
        $member = User::factory()->staff()->create();
        $member->assignRole($finance);

        $this->actingAs($this->superAdmin)
            ->deleteJson("api/v1/portal/admin/roles/{$finance->uuid}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ROLE_IN_USE')
            ->assertJsonPath('error.field', 'reassign_to');

        $this->actingAs($this->superAdmin)
            ->deleteJson("api/v1/portal/admin/roles/{$finance->uuid}", ['reassign_to' => $finance->uuid])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ROLE_NOT_FOUND');

        $this->actingAs($this->superAdmin)
            ->deleteJson("api/v1/portal/admin/roles/{$finance->uuid}", ['reassign_to' => $support->uuid])
            ->assertOk();

        $this->assertFalse(StaffRole::query()->whereKey($finance->id)->exists());
        $this->assertTrue($member->fresh()->hasRole($support->name));
        $this->assertSame(['holders' => 1, 'moved_to' => $support->uuid], collect(AuditEvent::query()->where('action', 'role.deleted')->first()->meta)->only(['moved_to', 'holders'])->sortKeys()->all());
    }

    public function test_a_role_manager_cannot_grant_what_they_do_not_hold(): void
    {
        $manager = User::factory()
            ->staff([AdminPermission::RolesView, AdminPermission::RolesManage, AdminPermission::OrdersView])
            ->create();

        $this->actingAs($manager)
            ->postJson('api/v1/portal/admin/roles', ['name' => 'Escalate', 'permissions' => ['orders.view', 'staff.manage']])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PERMISSION_NOT_HELD');

        $this->actingAs($manager)
            ->postJson('api/v1/portal/admin/roles', ['name' => 'Orders', 'permissions' => ['orders.view']])
            ->assertCreated();
    }

    public function test_roles_need_their_permissions_and_the_admin_role(): void
    {
        $reader = User::factory()->staff([AdminPermission::RolesView])->create();
        $pharmacy = User::factory()->portal(Role::Pharmacy)->create();
        $nobody = User::factory()->staff()->create();

        $this->actingAs($reader)->getJson('api/v1/portal/admin/roles')->assertOk();
        $this->actingAs($reader)
            ->postJson('api/v1/portal/admin/roles', ['name' => 'X', 'permissions' => []])
            ->assertForbidden();
        $this->actingAs($nobody)->getJson('api/v1/portal/admin/roles')->assertForbidden();
        $this->actingAs($nobody)->getJson('api/v1/portal/admin/permissions')->assertForbidden();
        $this->actingAs($pharmacy)->getJson('api/v1/portal/admin/roles')->assertForbidden();
    }
}

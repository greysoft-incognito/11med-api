<?php

namespace Tests\Feature\Staff;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Enums\AuthClient;
use App\Models\AuditEvent;
use App\Models\StaffRole;
use App\Models\User;
use App\Notifications\Identity\PortalPasswordLink;
use App\Services\Identity\SessionTokens;
use App\Services\Staff\PermissionHolders;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffTest extends TestCase
{
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->superAdmin = User::factory()
            ->admin()
            ->create(['full_name' => 'Ada Super']);
    }

    private function role(string $key): StaffRole
    {
        return StaffRole::query()->where('key', $key)->firstOrFail();
    }

    public function test_an_invite_opens_a_team_account_with_its_access_and_emails_the_link(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('api/v1/portal/admin/staff', [
                'email' => 'kemi@11med.ng',
                'name' => 'Kemi Bello',
                'roles' => [$this->role('finance')->uuid],
                'permissions' => ['audit.view'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'kemi@11med.ng')
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonPath('data.roles.0.name', 'Finance')
            ->assertJsonPath('data.permissions', ['audit.view']);

        $this->assertContains('payouts.settle', $response->json('data.effective_permissions'));
        $this->assertContains('audit.view', $response->json('data.effective_permissions'));

        $member = User::query()->where('email', 'kemi@11med.ng')->firstOrFail();
        $this->assertTrue($member->isStaff());
        $this->assertSame([], $member->kinds());
        Notification::assertSentTo($member, PortalPasswordLink::class);
        $this->assertSame('staff.invited', AuditEvent::query()->value('action'));
    }

    public function test_an_invite_refuses_a_taken_email_and_an_unknown_role(): void
    {
        User::factory()->app()->create(['email' => 'taken@11med.ng']);

        $this->actingAs($this->superAdmin)
            ->postJson('api/v1/portal/admin/staff', ['email' => 'Taken@11med.ng', 'name' => 'Someone', 'roles' => [], 'permissions' => []])
            ->assertConflict()
            ->assertJsonPath('error.code', 'EMAIL_TAKEN');

        $this->actingAs($this->superAdmin)
            ->postJson('api/v1/portal/admin/staff', ['email' => 'new@11med.ng', 'name' => 'Someone', 'roles' => ['0195f0a0-0000-7000-8000-000000000000'], 'permissions' => []])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ROLE_NOT_FOUND')
            ->assertJsonPath('error.field', 'roles');
    }

    public function test_the_list_is_paged_and_filtered(): void
    {
        $finance = $this->role('finance');
        $kemi = User::factory()->staff()->create(['full_name' => 'Kemi Bello', 'email' => 'kemi@11med.ng']);
        $kemi->assignRole($finance);
        User::factory()->staff()->create(['full_name' => 'Tunde Ade', 'deactivated_at' => now()]);
        User::factory()->portal(AccountKind::Pharmacy)->create();

        $this->actingAs($this->superAdmin)
            ->getJson('api/v1/portal/admin/staff?limit=2')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Ada Super');

        $this->actingAs($this->superAdmin)
            ->getJson("api/v1/portal/admin/staff?role={$finance->uuid}")
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $kemi->uuid);

        $this->actingAs($this->superAdmin)
            ->getJson('api/v1/portal/admin/staff?status=deactivated')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.status', 'deactivated');

        User::factory()->staff()->create(['full_name' => 'Ngozi Eze', 'password' => null]);

        $this->actingAs($this->superAdmin)
            ->getJson('api/v1/portal/admin/staff?status=invited')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.name', 'Ngozi Eze');

        $this->actingAs($this->superAdmin)
            ->getJson('api/v1/portal/admin/staff?status=active')
            ->assertJsonPath('data.total', 3);

        $this->actingAs($this->superAdmin)
            ->getJson('api/v1/portal/admin/staff?q=bello')
            ->assertJsonPath('data.total', 1);
    }

    public function test_access_is_replaced_and_takes_effect_on_the_routes(): void
    {
        $member = User::factory()->staff()->create();

        $this->actingAs($member)->getJson('api/v1/portal/admin/payouts')->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->putJson("api/v1/portal/admin/staff/{$member->uuid}/access", [
                'roles' => [$this->role('finance')->uuid],
                'permissions' => ['doctors.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['doctors.view']);

        $member->refresh()->unsetRelation('roles')->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($member)->getJson('api/v1/portal/admin/payouts')->assertOk();
        $this->actingAs($member)->getJson('api/v1/portal/admin/doctors')->assertOk();
        $this->actingAs($member)->getJson('api/v1/portal/admin/catalogue')->assertForbidden();

        $this->assertSame(
            ['permissions_added' => ['doctors.view'], 'permissions_removed' => [], 'roles_added' => ['Finance'], 'roles_removed' => []],
            collect(AuditEvent::query()->where('action', 'staff.access_changed')->first()->meta)->sortKeys()->all(),
        );
    }

    public function test_the_last_super_admin_keeps_the_role_and_stays_active(): void
    {
        User::query()->where('email', 'admin@example.com')->update(['deactivated_at' => now()]);

        $this->actingAs($this->superAdmin)
            ->putJson("api/v1/portal/admin/staff/{$this->superAdmin->uuid}/access", ['roles' => [], 'permissions' => []])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'LAST_SUPER_ADMIN');

        $this->actingAs($this->superAdmin)
            ->postJson("api/v1/portal/admin/staff/{$this->superAdmin->uuid}/deactivate")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'LAST_SUPER_ADMIN');

        $second = User::factory()->admin()->create();

        $this->actingAs($second)
            ->putJson("api/v1/portal/admin/staff/{$this->superAdmin->uuid}/access", ['roles' => [], 'permissions' => []])
            ->assertOk();
    }

    public function test_a_deactivated_super_admin_does_not_count(): void
    {
        User::query()->where('email', 'admin@example.com')->update(['deactivated_at' => now()]);
        $second = User::factory()->admin()->create();

        $this->actingAs($second)
            ->postJson("api/v1/portal/admin/staff/{$this->superAdmin->uuid}/deactivate")
            ->assertOk();

        $this->actingAs($second)
            ->postJson("api/v1/portal/admin/staff/{$second->uuid}/deactivate")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'LAST_SUPER_ADMIN');
    }

    public function test_only_a_super_admin_grants_or_removes_what_a_staff_manager_does_not_hold(): void
    {
        $manager = User::factory()
            ->staff([AdminPermission::StaffView, AdminPermission::StaffManage, AdminPermission::OrdersView])
            ->create();
        $member = User::factory()->staff()->create();

        $this->actingAs($manager)
            ->putJson("api/v1/portal/admin/staff/{$member->uuid}/access", ['roles' => [$this->role('super-admin')->uuid], 'permissions' => []])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PERMISSION_NOT_HELD')
            ->assertJsonPath('error.field', 'roles');

        $this->actingAs($manager)
            ->putJson("api/v1/portal/admin/staff/{$member->uuid}/access", ['roles' => [], 'permissions' => ['orders.view']])
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("api/v1/portal/admin/staff/{$this->superAdmin->uuid}/deactivate")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PERMISSION_NOT_HELD');
    }

    public function test_deactivation_ends_sessions_and_refuses_sign_in_until_reactivated(): void
    {
        $member = User::factory()->staff([AdminPermission::OrdersView])->create(['email' => 'desk@11med.ng']);
        app(SessionTokens::class)->issue($member, AuthClient::Portal);

        $this->actingAs($this->superAdmin)
            ->postJson("api/v1/portal/admin/staff/{$member->uuid}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'deactivated')
            ->assertJsonPath('data.effective_permissions', []);

        $this->assertSame(0, $member->tokens()->count());

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@11med.ng', 'password' => 'Password1'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_DEACTIVATED');

        $this->assertSame(0, app(PermissionHolders::class)->holders(AdminPermission::OrdersView)->whereKey($member->id)->count());

        $this->actingAs($this->superAdmin)
            ->postJson("api/v1/portal/admin/staff/{$member->uuid}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@11med.ng', 'password' => 'Password1'])
            ->assertCreated();

        $this->assertSame(['staff.deactivated', 'staff.reactivated'], AuditEvent::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_the_session_carries_the_effective_permissions(): void
    {
        $member = User::factory()->staff([AdminPermission::AuditView])->create(['email' => 'desk@11med.ng']);
        $member->assignRole($this->role('catalogue'));

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@11med.ng', 'password' => 'Password1'])
            ->assertCreated()
            ->assertJsonPath('data.account.permissions', ['summary.view', 'catalogue.view', 'catalogue.edit', 'audit.view']);

        $pharmacy = User::factory()->portal(AccountKind::Pharmacy)->create();

        $this->actingAs($pharmacy)
            ->getJson('api/v1/portal/auth/session')
            ->assertJsonPath('data.permissions', []);
    }

    public function test_holders_include_role_and_direct_permissions(): void
    {
        $direct = User::factory()->staff([AdminPermission::DoctorsReview])->create();
        $viaRole = User::factory()->staff()->create();
        $viaRole->assignRole($this->role('verification'));
        User::factory()->staff([AdminPermission::OrdersView])->create();

        $ids = app(PermissionHolders::class)
            ->holders(AdminPermission::DoctorsReview)
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$direct->id, $viaRole->id, $this->superAdmin->id, User::query()->where('email', 'admin@example.com')->value('id')],
            $ids,
        );
    }

    public function test_staff_routes_need_their_permissions(): void
    {
        $reader = User::factory()->staff([AdminPermission::StaffView])->create();
        $nobody = User::factory()->staff()->create();
        $pharmacy = User::factory()->portal(AccountKind::Pharmacy)->create();

        $this->actingAs($reader)->getJson('api/v1/portal/admin/staff')->assertOk();
        $this->actingAs($reader)->getJson("api/v1/portal/admin/staff/{$nobody->uuid}")->assertOk();
        $this->actingAs($reader)
            ->postJson("api/v1/portal/admin/staff/{$nobody->uuid}/deactivate")
            ->assertForbidden();
        $this->actingAs($nobody)->getJson('api/v1/portal/admin/staff')->assertForbidden();
        $this->actingAs($pharmacy)->getJson('api/v1/portal/admin/staff')->assertForbidden();
        $this->actingAs($reader)
            ->getJson("api/v1/portal/admin/staff/{$pharmacy->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'STAFF_NOT_FOUND');
    }
}

<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Enums\PartnerKind;
use App\Models\User;
use App\Notifications\Identity\PortalAccessGranted;
use App\Notifications\Identity\PortalPasswordLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\TestCase;

class AccountKindsTest extends TestCase
{
    use MakesPartners;

    private const INSTALL = '0190a9b8-0d8e-7c43-8a2b-4c4f1f3c5d6e';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function person(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create(['email' => 'ada@example.com', 'email_verified_at' => now()]);
    }

    private function appToken(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('api/v1/auth/login', ['phone_number' => $user->phone, 'password' => 'Password1', 'device_id' => self::INSTALL])
            ->assertOk()
            ->json('data.access_token');
    }

    private function portalToken(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('api/v1/portal/auth/sign-in', ['email' => $user->email, 'password' => 'Password1'])
            ->assertCreated()
            ->json('data.access_token');
    }

    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_one_account_signs_in_to_each_side_it_holds_and_each_token_stays_on_its_side(): void
    {
        $user = $this->person();
        $user->grantKind(AccountKind::Doctor);
        $user->grantKind(AccountKind::Pharmacy);

        $app = $this->appToken($user);
        $portal = $this->portalToken($user);

        $this->as($app)
            ->getJson('api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('data.user.kinds', ['patient', 'doctor']);
        $this->as($app)->getJson('api/v1/doctor/profile')->assertOk();
        $this->as($app)->getJson('api/v1/portal/auth/session')->assertForbidden();
        $this->as($app)->getJson('api/v1/portal/notifications')->assertForbidden();

        $this->as($portal)
            ->getJson('api/v1/portal/auth/session')
            ->assertOk()
            ->assertJsonPath('data.kinds', ['pharmacy'])
            ->assertJsonPath('data.permissions', []);
        $this->as($portal)->getJson('api/v1/auth/session')->assertForbidden();
        $this->as($portal)->getJson('api/v1/doctor/profile')->assertForbidden();
    }

    public function test_staff_access_opens_the_portal_and_the_desk_to_a_patient_and_only_from_a_portal_session(): void
    {
        $user = $this->person();
        $user->forceFill(['staff_since' => now()])->save();
        $user->givePermissionTo(AdminPermission::SummaryView->value);

        $portal = $this->portalToken($user);
        $app = $this->appToken($user);

        $this->as($portal)
            ->getJson('api/v1/portal/auth/session')
            ->assertOk()
            ->assertJsonPath('data.kinds', [])
            ->assertJsonPath('data.permissions', ['summary.view']);
        $this->as($portal)->getJson('api/v1/portal/admin/summary')->assertOk();
        $this->as($app)->getJson('api/v1/portal/admin/summary')->assertForbidden();
        $this->as($app)->getJson('api/v1/auth/session')->assertOk();
    }

    public function test_an_app_account_without_staff_access_or_a_partner_cannot_sign_in_to_the_portal(): void
    {
        $user = $this->person();

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => $user->email, 'password' => 'Password1'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_deactivation_ends_the_portal_sessions_and_the_desk_and_leaves_the_app(): void
    {
        $user = $this->person();
        $user->forceFill(['staff_since' => now()])->save();
        $user->givePermissionTo(AdminPermission::SummaryView->value);

        $portal = $this->portalToken($user);
        $app = $this->appToken($user);

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/staff/{$user->uuid}/deactivate")
            ->assertOk();

        $this->as($portal)->getJson('api/v1/portal/auth/session')->assertUnauthorized();
        $this->as($app)->getJson('api/v1/auth/session')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => $user->email, 'password' => 'Password1'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_DEACTIVATED');
    }

    public function test_two_step_is_required_for_the_desk_only(): void
    {
        config(['elevenmed.two_factor.admin_required' => true]);

        $user = $this->person();
        $user->forceFill(['staff_since' => now()])->save();
        $user->givePermissionTo(AdminPermission::SummaryView->value);

        $this->as($this->appToken($user))->getJson('api/v1/auth/session')->assertOk();

        $portal = $this->portalToken($user);

        $this->as($portal)
            ->getJson('api/v1/portal/admin/summary')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TWO_FACTOR_SETUP_REQUIRED');
        $this->as($portal)
            ->getJson('api/v1/portal/auth/session')
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true);
    }

    public function test_a_staff_invite_links_a_verified_account_and_tells_it_instead_of_sending_a_link(): void
    {
        $user = $this->person();

        $this->actingAs($this->admin())
            ->postJson('api/v1/portal/admin/staff', ['email' => 'ADA@example.com', 'name' => 'Ada', 'roles' => [], 'permissions' => ['audit.view']])
            ->assertCreated()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.status', 'active');

        $user->refresh();
        $this->assertTrue($user->isStaff());
        $this->assertSame([AccountKind::Patient], $user->kinds());
        Notification::assertSentTo($user, PortalAccessGranted::class);
        Notification::assertNotSentTo($user, PortalPasswordLink::class);

        $this->postJson('api/v1/portal/admin/staff', ['email' => 'ada@example.com', 'name' => 'Ada', 'roles' => [], 'permissions' => []])
            ->assertConflict()
            ->assertJsonPath('error.code', 'EMAIL_TAKEN');
    }

    public function test_an_invite_to_an_unverified_email_is_refused(): void
    {
        User::factory()
            ->app(AccountKind::Patient)
            ->create(['email' => 'ada@example.com', 'email_verified_at' => null]);

        $this->actingAs($this->admin())
            ->postJson('api/v1/portal/admin/staff', ['email' => 'ada@example.com', 'name' => 'Ada', 'roles' => [], 'permissions' => []])
            ->assertConflict()
            ->assertJsonPath('error.code', 'EMAIL_TAKEN')
            ->assertJsonPath('error.field', 'email');
    }

    public function test_a_partner_invite_links_a_verified_account_and_removal_keeps_it(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        [, $owner] = $this->approvedPartner(PartnerKind::Pharmacy);
        $user = $this->person();

        $this->actingAs($owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Ada', 'email' => 'ada@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.id', $user->uuid);

        $user->refresh();
        $this->assertSame([AccountKind::Patient, AccountKind::Pharmacy], $user->kinds());
        Notification::assertSentTo($user, PortalAccessGranted::class);

        $this->actingAs($owner)
            ->deleteJson("api/v1/portal/staff/{$user->uuid}")
            ->assertOk();

        $user->refresh();
        $this->assertFalse($user->trashed());
        $this->assertNull($user->organisation_id);
        $this->assertSame([AccountKind::Patient], $user->kinds());
    }

    public function test_erasure_is_refused_while_the_account_holds_portal_access(): void
    {
        $user = $this->person();
        $user->grantKind(AccountKind::Laboratory);

        $this->actingAs($user)
            ->deleteJson('api/v1/auth/account', ['password' => 'Password1'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PORTAL_ACCESS_HELD');

        $user->revokeKind(AccountKind::Laboratory);
        $user->forceFill(['staff_since' => now()])->save();

        $this->actingAs($user->refresh())
            ->deleteJson('api/v1/auth/account', ['password' => 'Password1'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PORTAL_ACCESS_HELD');
    }

    public function test_a_patient_opens_the_doctor_side(): void
    {
        $user = $this->person();

        $this->actingAs($user)
            ->postJson('api/v1/auth/kinds', ['kind' => 'doctor'])
            ->assertOk()
            ->assertJsonPath('data.kinds', ['patient', 'doctor']);

        $this->postJson('api/v1/auth/kinds', ['kind' => 'doctor'])
            ->assertOk()
            ->assertJsonPath('data.kinds', ['patient', 'doctor']);

        $this->postJson('api/v1/auth/kinds', ['kind' => 'pharmacy'])
            ->assertBadRequest()
            ->assertJsonPath('error.field', 'kind');
    }

    public function test_each_side_lists_its_own_notification_categories(): void
    {
        $user = $this->person();
        $user->grantKind(AccountKind::Pharmacy);

        $app = $this->as($this->appToken($user))
            ->getJson("api/v1/notifications/{$user->uuid}/preferences")
            ->assertOk()
            ->json('data.preferences');

        $portal = $this->as($this->portalToken($user))
            ->getJson('api/v1/portal/notifications/preferences')
            ->assertOk()
            ->json('data.preferences');

        $this->assertContains('medication_reminder', array_column($app, 'category'));
        $this->assertNotContains('pharmacy_order', array_column($app, 'category'));
        $this->assertSame(['pharmacy_order', 'account', 'announcement'], array_column($portal, 'category'));
    }
}

<?php

namespace Tests\Feature\Accounts;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Enums\AuthClient;
use App\Enums\VerificationStatus;
use App\Models\DoctorProfile;
use App\Models\RefreshToken;
use App\Models\User;
use App\Notifications\Accounts\AccountStatusChanged;
use App\Notifications\Identity\EmailCode;
use App\Notifications\Identity\PhoneCode;
use App\Services\Audit\AuditLog;
use App\Services\Identity\SessionTokens;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\MakesAdmins;
use Tests\Fakes\RecordingAuditLog;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use MakesAdmins;

    private User $admin;

    private RecordingAuditLog $audit;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo('2026-12-01T09:00:00Z');

        $this->audit = new RecordingAuditLog;
        $this->app->instance(AuditLog::class, $this->audit);

        $this->admin = $this->teamMember();
    }

    public function test_the_directory_lists_app_accounts_newest_first_and_searches(): void
    {
        $ada = $this->patient(['full_name' => 'Ada Obi', 'phone' => '08031112222', 'email' => 'ada@example.com']);
        $this->travel(1)->minutes();
        $this->doctor(['full_name' => 'Chidi Eze', 'phone' => '08031113333']);
        User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create(['full_name' => 'Ada Pharmacy']);

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/users?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.full_name', 'Chidi Eze')
            ->assertJsonPath('data.items.0.kinds', ['doctor'])
            ->assertJsonPath('data.next_cursor', fn($cursor) => is_string($cursor));

        foreach (['ada', '+2348031112222', '1112', 'ADA@example'] as $q) {
            $this->actingAs($this->admin)
                ->getJson('api/v1/portal/admin/users?q=' . urlencode($q))
                ->assertOk()
                ->assertJsonPath('data.total', 1)
                ->assertJsonPath('data.items.0.id', $ada->uuid);
        }

        $this->actingAs($this->admin)
            ->getJson('api/v1/portal/admin/users?kind=doctor')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.doctor_status', 'approved');
    }

    public function test_an_account_shows_its_standing_and_counts_without_medical_data(): void
    {
        $patient = $this->patient(['full_name' => 'Ada Obi', 'phone' => '08031112222']);
        app(SessionTokens::class)->issue($patient, AuthClient::App);

        $this->actingAs($this->admin)
            ->getJson("api/v1/portal/admin/users/{$patient->uuid}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'OK',
                'timestamp' => now()->toIso8601ZuluString('millisecond'),
                'data' => [
                    'id' => $patient->uuid,
                    'kinds' => ['patient'],
                    'full_name' => 'Ada Obi',
                    'phone' => '08031112222',
                    'email' => null,
                    'phone_verified' => true,
                    'email_verified' => false,
                    'is_premium' => false,
                    'doctor_status' => null,
                    'suspended_at' => null,
                    'suspension_reason' => null,
                    'created_at' => '2026-12-01T09:00:00.000Z',
                    'last_active_at' => '2026-12-01T09:00:00.000Z',
                    'counts' => ['orders' => 0, 'lab_bookings' => 0, 'consultations' => 0],
                ],
            ]);
    }

    public function test_suspending_ends_every_session_and_refuses_sign_in(): void
    {
        $patient = $this->patient(['phone' => '08031112222']);
        app(SessionTokens::class)->issue($patient, AuthClient::App);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$patient->uuid}/suspend", ['reason' => 'Repeated chargebacks on orders.'])
            ->assertOk()
            ->assertJsonPath('data.suspended_at', '2026-12-01T09:00:00.000Z')
            ->assertJsonPath('data.suspension_reason', 'Repeated chargebacks on orders.');

        $this->assertSame(0, $patient->tokens()->count());
        $this->assertSame(0, RefreshToken::query()->where('user_id', $patient->id)->whereNull('revoked_at')->count());
        $this->assertSame(['user.suspended'], $this->audit->actions());
        Notification::assertSentOnDemand(AccountStatusChanged::class, fn($n) => $n->suspended);

        $this->app->forgetInstance('auth');

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031112222', 'password' => 'Password1', 'device_id' => 'install'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031112222', 'password' => 'wrong-one', 'device_id' => 'install'])
            ->assertUnauthorized();

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$patient->uuid}/suspend", ['reason' => 'Repeated chargebacks on orders.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_SUSPENDED');
    }

    public function test_reinstating_lets_the_account_sign_in_again(): void
    {
        $patient = $this->patient(['phone' => '08031112222', 'suspended_at' => now(), 'suspension_reason' => 'Under review by the team.']);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$patient->uuid}/reinstate")
            ->assertOk()
            ->assertJsonPath('data.suspended_at', null)
            ->assertJsonPath('data.suspension_reason', null);

        $this->assertSame(['user.reinstated'], $this->audit->actions());
        Notification::assertSentOnDemand(AccountStatusChanged::class, fn($n) => ! $n->suspended);

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031112222', 'password' => 'Password1', 'device_id' => 'install'])->assertOk();

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$patient->uuid}/reinstate")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'NOT_SUSPENDED');
    }

    public function test_force_sign_out_ends_sessions_without_suspending(): void
    {
        $patient = $this->patient();
        app(SessionTokens::class)->issue($patient, AuthClient::App);
        app(SessionTokens::class)->issue($patient, AuthClient::App);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$patient->uuid}/sign-out")
            ->assertOk()
            ->assertJsonPath('data.suspended_at', null);

        $this->assertSame(0, $patient->tokens()->count());
        $this->assertSame(['user.signed_out'], $this->audit->actions());
    }

    public function test_codes_are_sent_to_the_person_within_their_budget(): void
    {
        $unverified = $this->patient(['phone' => '08031112222', 'phone_verified_at' => null, 'email' => 'ada@example.com']);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$unverified->uuid}/verification", ['channel' => 'phone'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['expires_in', 'resends_remaining']])
            ->assertJsonMissingPath('data.code');

        Notification::assertSentOnDemand(PhoneCode::class);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$unverified->uuid}/verification", ['channel' => 'email'])
            ->assertOk();

        Notification::assertSentTo($unverified, EmailCode::class);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$unverified->uuid}/password-reset")
            ->assertOk()
            ->assertJsonPath('data.resends_remaining', 1);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$unverified->uuid}/password-reset")
            ->assertOk()
            ->assertJsonPath('data.resends_remaining', 0);

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$unverified->uuid}/password-reset")
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_OTP_RESEND_LIMIT');

        $this->assertSame(['user.verification_sent', 'user.verification_sent', 'user.password_reset_sent', 'user.password_reset_sent'], $this->audit->actions());

        $verified = $this->patient();

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$verified->uuid}/verification", ['channel' => 'phone'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PHONE_VERIFIED');

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$verified->uuid}/verification", ['channel' => 'email'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'NO_EMAIL');

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$verified->uuid}/verification", ['channel' => 'fax'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'channel');
    }

    public function test_the_reason_is_required_and_only_app_accounts_are_found(): void
    {
        $patient = $this->patient();
        $pharmacy = User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create();

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$patient->uuid}/suspend", ['reason' => 'too short'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'reason');

        foreach (["api/v1/portal/admin/users/{$pharmacy->uuid}", 'api/v1/portal/admin/users/0193a0a0-0000-7000-8000-000000000000'] as $url) {
            $this->actingAs($this->admin)
                ->getJson($url)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'USER_NOT_FOUND');
        }

        $this->actingAs($this->admin)
            ->postJson("api/v1/portal/admin/users/{$pharmacy->uuid}/sign-out")
            ->assertNotFound();
    }

    public function test_other_roles_are_refused(): void
    {
        $patient = $this->patient();

        foreach ([AccountKind::Patient, AccountKind::Doctor] as $role) {
            $this->actingAs(User::factory()->app($role)->create())
                ->getJson('api/v1/portal/admin/users')
                ->assertForbidden();
        }

        foreach ([AccountKind::Pharmacy, AccountKind::Laboratory] as $role) {
            $this->actingAs(User::factory()->portal($role)->create())
                ->postJson("api/v1/portal/admin/users/{$patient->uuid}/sign-out")
                ->assertForbidden();
        }
    }

    public function test_viewing_does_not_allow_managing(): void
    {
        $viewer = $this->teamMember(AdminPermission::UsersView);
        $patient = $this->patient();

        $this->actingAs($viewer)
            ->getJson("api/v1/portal/admin/users/{$patient->uuid}")
            ->assertOk();

        $this->actingAs($viewer)
            ->postJson("api/v1/portal/admin/users/{$patient->uuid}/suspend", ['reason' => 'Repeated chargebacks on orders.'])
            ->assertForbidden();

        $this->actingAs($this->teamMember(AdminPermission::OrdersView))
            ->getJson('api/v1/portal/admin/users')
            ->assertForbidden();
    }

    public function test_each_route_names_its_permission(): void
    {
        $expected = [
            'v1.portal.admin.users.index' => 'permission:users.view',
            'v1.portal.admin.users.show' => 'permission:users.view',
            'v1.portal.admin.users.suspend' => 'permission:users.manage',
            'v1.portal.admin.users.reinstate' => 'permission:users.manage',
            'v1.portal.admin.users.sign-out' => 'permission:users.manage',
            'v1.portal.admin.users.verification' => 'permission:users.manage',
            'v1.portal.admin.users.password-reset' => 'permission:users.manage',
        ];

        foreach ($expected as $name => $middleware) {
            $this->assertContains($middleware, Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    private function patient(array $attributes = []): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create($attributes);
    }

    private function doctor(array $attributes = []): User
    {
        $doctor = User::factory()
            ->app(AccountKind::Doctor)
            ->create($attributes);

        DoctorProfile::query()->forceCreate([
            'user_id' => $doctor->id,
            'languages' => [],
            'availability' => [],
            'verification_status' => VerificationStatus::Approved,
        ]);

        return $doctor;
    }
}

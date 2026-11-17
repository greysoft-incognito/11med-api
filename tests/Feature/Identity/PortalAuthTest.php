<?php

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\PasswordLink;
use App\Models\User;
use App\Notifications\Identity\PortalPasswordLink;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PortalAuthTest extends TestCase
{
    private User $account;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->account = User::factory()
            ->portal(Role::Pharmacy)
            ->create(['email' => 'desk@pharmacy.ng', 'full_name' => 'Ada Desk']);
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function signIn(): array
    {
        return $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'Desk@Pharmacy.ng', 'password' => 'Password1'])
            ->assertCreated()
            ->json('data');
    }

    private function linkFromEmail(): string
    {
        $url = null;

        Notification::assertSentTo($this->account, PortalPasswordLink::class, function (PortalPasswordLink $n) use (&$url) {
            $url = $n->url;

            return true;
        });

        return str($url)->after('token=')->toString();
    }

    public function test_sign_in_answers_a_session_with_the_account(): void
    {
        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@pharmacy.ng', 'password' => 'Password1'])
            ->assertCreated()
            ->assertJsonPath('data.account.email', 'desk@pharmacy.ng')
            ->assertJsonPath('data.account.name', 'Ada Desk')
            ->assertJsonPath('data.account.role', 'pharmacy')
            ->assertJsonPath('data.account.organisation_id', null)
            ->assertJsonPath('data.account.organisation_name', null)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'account' => ['id', 'created_at']]]);
    }

    public function test_a_wrong_password_or_an_app_account_is_invalid_credentials(): void
    {
        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@pharmacy.ng', 'password' => 'nope'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        User::factory()
            ->app()
            ->create(['email' => 'patient@example.com']);

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'patient@example.com', 'password' => 'Password1'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_sign_in_is_rate_limited(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@pharmacy.ng', 'password' => 'nope']);
        }

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@pharmacy.ng', 'password' => 'Password1'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_session_returns_the_account_and_says_when_the_token_expired(): void
    {
        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->getJson('api/v1/portal/auth/session')
            ->assertOk()
            ->assertJsonPath('data.email', 'desk@pharmacy.ng');

        $this->travel(16)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($session['access_token'])
            ->getJson('api/v1/portal/auth/session')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'TOKEN_EXPIRED');
    }

    public function test_session_refuses_an_app_account(): void
    {
        $patient = User::factory()
            ->app()
            ->create();

        $this->actingAs($patient)
            ->getJson('api/v1/portal/auth/session')
            ->assertForbidden();
    }

    public function test_refresh_answers_a_new_session_and_a_reused_token_signs_nobody_in(): void
    {
        $session = $this->signIn();

        $next = $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk()
            ->assertJsonPath('data.account.email', 'desk@pharmacy.ng')
            ->json('data');

        $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_REFRESH');

        $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $next['refresh_token']])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_REFRESH');
    }

    public function test_sign_out_ends_the_pair(): void
    {
        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->postJson('api/v1/portal/auth/sign-out')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized();
    }

    public function test_forgot_answers_the_same_for_any_address_and_emails_only_an_account(): void
    {
        $this->postJson('api/v1/portal/auth/forgot', ['email' => 'nobody@pharmacy.ng'])
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.dev_link', null);

        Notification::assertNothingSent();

        $this->postJson('api/v1/portal/auth/forgot', ['email' => 'desk@pharmacy.ng'])
            ->assertOk()
            ->assertJsonPath('data', ['sent' => true, 'dev_link' => null]);

        Notification::assertSentTo($this->account, PortalPasswordLink::class);
    }

    public function test_the_emailed_link_sets_the_password_once_and_signs_in(): void
    {
        $old = $this->signIn();

        $this->postJson('api/v1/portal/auth/forgot', ['email' => 'desk@pharmacy.ng'])->assertOk();
        $token = $this->linkFromEmail();

        $this->postJson('api/v1/portal/auth/set-password', ['token' => $token, 'password' => 'newpass99'])
            ->assertOk()
            ->assertJsonPath('data.account.email', 'desk@pharmacy.ng')
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);

        $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $old['refresh_token']])->assertUnauthorized();

        $this->postJson('api/v1/portal/auth/set-password', ['token' => $token, 'password' => 'another99'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RESET_INVALID')
            ->assertJsonPath('error.field', 'token');

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@pharmacy.ng', 'password' => 'newpass99'])->assertCreated();
    }

    public function test_a_reset_link_lapses_after_an_hour_and_the_password_needs_a_letter_and_digit(): void
    {
        $this->postJson('api/v1/portal/auth/forgot', ['email' => 'desk@pharmacy.ng'])->assertOk();
        $token = $this->linkFromEmail();

        $this->postJson('api/v1/portal/auth/set-password', ['token' => $token, 'password' => 'lettersonly'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'password');

        $this->travel(61)->minutes();

        $this->postJson('api/v1/portal/auth/set-password', ['token' => $token, 'password' => 'newpass99'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RESET_INVALID');
    }

    public function test_create_admin_opens_a_team_account_with_a_week_long_link(): void
    {
        $this->artisan('app:create-admin', ['email' => 'ops@11med.ng', 'name' => 'Ops Team'])
            ->assertSuccessful();

        $admin = User::firstWhere('email', 'ops@11med.ng');

        $this->assertSame(Role::Admin, $admin->role());
        $this->assertNull($admin->password);
        $this->assertTrue(PasswordLink::firstWhere('user_id', $admin->id)->expires_at->isAfter(now()->addDays(6)));

        $this->artisan('app:create-admin', ['email' => 'OPS@11med.ng', 'name' => 'Again'])
            ->assertFailed();
    }
}

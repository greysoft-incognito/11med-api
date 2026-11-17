<?php

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\Identity\EmailCode;
use App\Notifications\Identity\PhoneCode;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppPasswordAndEmailTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['elevenmed.otp.fixed_code' => '123456']);

        $this->user = User::factory()
            ->app()
            ->create(['phone' => '08031234567']);
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function signIn(string $password = 'Password1'): array
    {
        return $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => $password, 'device_id' => 'x'])
            ->assertOk()
            ->json('data');
    }

    public function test_forgot_password_texts_a_code_and_never_says_whether_the_number_exists(): void
    {
        $this->postJson('api/v1/auth/forgot-password', ['phone_number' => '08031234567'])
            ->assertOk()
            ->assertJsonPath('data.otp_expires_in', 600);

        $this->postJson('api/v1/auth/forgot-password', ['phone_number' => '08099999999'])
            ->assertOk()
            ->assertJsonPath('data.otp_expires_in', 600);

        Notification::assertSentOnDemandTimes(PhoneCode::class, 1);
    }

    public function test_forgot_password_shares_the_resend_budget(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->postJson('api/v1/auth/forgot-password', ['phone_number' => '08031234567'])->assertOk();
        }

        $this->postJson('api/v1/auth/forgot-password', ['phone_number' => '08031234567'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_OTP_RESEND_LIMIT');
    }

    public function test_reset_sets_the_password_clears_the_lock_and_ends_every_session(): void
    {
        $old = $this->signIn();

        foreach (range(1, 10) as $ignored) {
            $this->travel(16)->minutes();
            $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Wrong1234', 'device_id' => 'x']);
        }

        $this->postJson('api/v1/auth/forgot-password', ['phone_number' => '08031234567'])->assertOk();

        $this->postJson('api/v1/auth/reset-password', ['phone_number' => '08031234567', 'code' => '123456', 'password' => 'Newpass99'])
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $old['refresh_token']])->assertUnauthorized();

        $this->signIn('Newpass99');
    }

    public function test_reset_refuses_a_wrong_code_or_an_unknown_number_alike(): void
    {
        $this->postJson('api/v1/auth/forgot-password', ['phone_number' => '08031234567'])->assertOk();

        $this->postJson('api/v1/auth/reset-password', ['phone_number' => '08031234567', 'code' => '000000', 'password' => 'Newpass99'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'AUTH_INVALID_OTP')
            ->assertJsonPath('error.field', 'code');

        $this->postJson('api/v1/auth/reset-password', ['phone_number' => '08099999999', 'code' => '123456', 'password' => 'Newpass99'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'AUTH_INVALID_OTP')
            ->assertJsonPath('error.field', 'code');
    }

    public function test_change_password_keeps_this_session_and_ends_the_others(): void
    {
        $other = $this->signIn();
        $this_ = $this->signIn();

        $this->withToken($this_['access_token'])
            ->postJson('api/v1/auth/change-password', ['current_password' => 'Password1', 'new_password' => 'Newpass99'])
            ->assertOk();

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $other['refresh_token']])->assertUnauthorized();
        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $this_['refresh_token']])->assertOk();
    }

    public function test_change_password_needs_the_current_one_and_a_new_one(): void
    {
        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/change-password', ['current_password' => 'Wrong1234', 'new_password' => 'Newpass99'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT')
            ->assertJsonPath('error.field', 'current_password');

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/change-password', ['current_password' => 'Password1', 'new_password' => 'Password1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_UNCHANGED')
            ->assertJsonPath('error.field', 'new_password');

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/change-password', ['current_password' => 'Password1', 'new_password' => 'short'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'new_password');
    }

    public function test_an_email_is_unconfirmed_until_its_code_comes_back(): void
    {
        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->patchJson('api/v1/auth/email', ['email' => 'amaka@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'amaka@example.com')
            ->assertJsonPath('data.email_verified', false);

        Notification::assertSentTo($this->user, EmailCode::class);

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/email/verify', ['code' => '000000'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'AUTH_INVALID_OTP')
            ->assertJsonPath('error.field', 'code');

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/email/verify', ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/email/resend')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EMAIL_VERIFIED');
    }

    public function test_saving_the_same_address_sends_nothing_and_removing_it_clears_it(): void
    {
        $this->user->forceFill(['email' => 'amaka@example.com', 'email_verified_at' => now()])->save();
        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->patchJson('api/v1/auth/email', ['email' => 'AMAKA@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        Notification::assertNothingSent();

        $this->withToken($session['access_token'])
            ->patchJson('api/v1/auth/email', ['email' => null])
            ->assertOk()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.email_verified', false);

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/email/verify', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_EMAIL');
    }

    public function test_an_address_on_another_account_is_taken(): void
    {
        User::factory()
            ->app()
            ->create(['email' => 'amaka@example.com']);

        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->patchJson('api/v1/auth/email', ['email' => 'Amaka@Example.com'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'EMAIL_TAKEN')
            ->assertJsonPath('error.field', 'email');
    }

    public function test_email_resends_have_a_budget(): void
    {
        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->patchJson('api/v1/auth/email', ['email' => 'amaka@example.com'])
            ->assertOk();

        foreach ([2, 1, 0] as $remaining) {
            $this->withToken($session['access_token'])
                ->postJson('api/v1/auth/email/resend')
                ->assertOk()
                ->assertJsonPath('data.resends_remaining', $remaining);
        }

        $this->withToken($session['access_token'])
            ->postJson('api/v1/auth/email/resend')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_OTP_RESEND_LIMIT');
    }

    public function test_the_signed_in_routes_need_an_app_account(): void
    {
        $this->postJson('api/v1/auth/change-password')->assertUnauthorized();
        $this->patchJson('api/v1/auth/email')->assertUnauthorized();

        $pharmacy = User::factory()
            ->portal(Role::Pharmacy)
            ->create();

        $this->actingAs($pharmacy)
            ->postJson('api/v1/auth/email/resend')
            ->assertForbidden();
    }
}

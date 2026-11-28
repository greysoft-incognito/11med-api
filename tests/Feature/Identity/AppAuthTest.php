<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountKind;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Notifications\Identity\PhoneCode;
use Database\Seeders\DocumentSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppAuthTest extends TestCase
{
    private const INSTALL = '0b7f0f0e-5d6a-4c43-9d1e-2f4a8f0c9a11';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['elevenmed.otp.fixed_code' => '123456']);
    }

    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson('api/v1/auth/register', $overrides + [
            'full_name' => 'Amaka Obi',
            'phone_number' => '+2348031234567',
            'password' => 'Password1',
            'role' => 'patient',
            'device_id' => self::INSTALL,
        ]);
    }

    public function test_registration_holds_the_account_for_its_phone_code(): void
    {
        $this->seed(DocumentSeeder::class);

        $this->register()
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone_number', '08031234567')
            ->assertJsonPath('data.otp_expires_in', 600)
            ->assertJsonStructure(['data' => ['user_id']]);

        $user = User::firstWhere('phone', '08031234567');

        $this->assertSame([AccountKind::Patient], $user->kinds());
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('1.1', ConsentRecord::firstWhere('user_id', $user->id)->policy_version);

        Notification::assertSentOnDemand(PhoneCode::class, fn($n, $channels, $notifiable) => $notifiable->routes['sms'] === '+2348031234567');
    }

    public function test_registration_records_the_consent_the_client_sends(): void
    {
        $this->register(['consent' => ['policy_version' => '2026-09-01', 'accepted_at' => '2026-11-17T08:00:00.000Z']])
            ->assertCreated();

        $consent = ConsentRecord::sole();

        $this->assertSame('2026-09-01', $consent->policy_version);
        $this->assertSame('2026-11-17T08:00:00+00:00', $consent->accepted_at->toIso8601String());
    }

    public function test_registration_validates_like_the_contract(): void
    {
        $this->register(['phone_number' => '0123'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'phone_number');

        $this->register(['password' => 'password1'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'password');

        $this->register(['role' => 'admin'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'role');
    }

    public function test_a_verified_number_cannot_register_again(): void
    {
        User::factory()
            ->app()
            ->create(['phone' => '08031234567']);

        $this->register()
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PHONE_ALREADY_REGISTERED')
            ->assertJsonPath('error.field', 'phone_number');
    }

    public function test_an_abandoned_sign_up_does_not_block_its_number(): void
    {
        $this->register(['full_name' => 'Someone Else'])->assertCreated();
        $this->register()->assertCreated();

        $this->assertSame('Amaka Obi', User::firstWhere('phone', '08031234567')->full_name);
        $this->assertSame(1, User::withTrashed()->where('phone', '08031234567')->count());
    }

    public function test_the_phone_code_opens_a_session(): void
    {
        $this->register()->assertCreated();

        $this->withHeader('X-Install-Id', self::INSTALL)
            ->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.user.full_name', 'Amaka Obi')
            ->assertJsonPath('data.user.phone_number', '08031234567')
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonPath('data.user.kinds', ['patient'])
            ->assertJsonPath('data.user.has_medical_profile', false)
            ->assertJsonPath('data.user.is_premium', false)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'user' => ['id']]]);

        $this->assertNotNull(User::firstWhere('phone', '08031234567')->phone_verified_at);
    }

    public function test_a_wrong_or_expired_code_is_refused_on_the_code_field(): void
    {
        $this->register()->assertCreated();

        $this->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '000000'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'AUTH_INVALID_OTP')
            ->assertJsonPath('error.field', 'code');

        $this->travel(11)->minutes();

        $this->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '123456'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'AUTH_OTP_EXPIRED');
    }

    public function test_five_wrong_tries_spend_the_code(): void
    {
        $this->register()->assertCreated();

        foreach (range(1, 5) as $ignored) {
            $this->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '000000']);
        }

        $this->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '123456'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'AUTH_INVALID_OTP');
    }

    public function test_verifying_an_unknown_number_is_not_found(): void
    {
        $this->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '123456'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'USER_NOT_FOUND')
            ->assertJsonPath('error.field', 'phone_number');
    }

    public function test_three_resends_in_thirty_minutes_then_a_wait(): void
    {
        $this->register()->assertCreated();

        foreach ([2, 1, 0] as $remaining) {
            $this->postJson('api/v1/auth/resend-otp', ['phone_number' => '08031234567'])
                ->assertOk()
                ->assertJsonPath('data.otp_expires_in', 600)
                ->assertJsonPath('data.resends_remaining', $remaining);
        }

        $wait = $this->postJson('api/v1/auth/resend-otp', ['phone_number' => '08031234567'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_OTP_RESEND_LIMIT')
            ->json('error.meta.retry_after_ms');

        $this->assertGreaterThan(29 * 60_000, $wait);

        $this->travel(31)->minutes();

        $this->postJson('api/v1/auth/resend-otp', ['phone_number' => '08031234567'])->assertOk();
    }

    public function test_resending_to_an_unknown_number_answers_the_same_and_texts_nobody(): void
    {
        $this->postJson('api/v1/auth/resend-otp', ['phone_number' => '08031234567'])
            ->assertOk()
            ->assertJsonPath('data.resends_remaining', 2);

        Notification::assertNothingSent();
    }

    public function test_login_opens_a_session(): void
    {
        User::factory()
            ->app(AccountKind::Doctor)
            ->create(['phone' => '08031234567']);

        $this->postJson('api/v1/auth/login', ['phone_number' => '+2348031234567', 'password' => 'Password1', 'device_id' => self::INSTALL])
            ->assertOk()
            ->assertJsonPath('data.user.kinds', ['doctor']);
    }

    public function test_failed_logins_count_down_then_lock(): void
    {
        User::factory()
            ->app()
            ->create(['phone' => '08031234567']);

        foreach ([4, 3, 2, 1, 0] as $remaining) {
            $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Wrong1234', 'device_id' => 'x'])
                ->assertUnauthorized()
                ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS')
                ->assertJsonPath('error.meta.attempts_remaining', $remaining);
        }

        $locked = $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => 'x'])
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'AUTH_ACCOUNT_LOCKED')
            ->json('error.meta.locked_for_ms');

        $this->assertGreaterThan(14 * 60_000, $locked);

        $this->travel(16)->minutes();

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => 'x'])
            ->assertOk();
    }

    public function test_ten_failures_lock_until_the_password_is_reset(): void
    {
        foreach (range(1, 9) as $attempt) {
            $this->travel(16)->minutes();
            $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Wrong1234', 'device_id' => 'x']);
        }

        $this->travel(16)->minutes();

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Wrong1234', 'device_id' => 'x'])
            ->assertStatus(423)
            ->assertJsonPath('error.meta.requires_email_unlock', true);

        $this->travel(1)->days();

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Wrong1234', 'device_id' => 'x'])
            ->assertStatus(423);
    }

    public function test_an_unverified_account_is_sent_a_code_instead_of_a_session(): void
    {
        $this->register()->assertCreated();

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PHONE_NOT_VERIFIED');

        Notification::assertSentOnDemandTimes(PhoneCode::class, 2);
    }

    public function test_a_portal_account_cannot_sign_in_to_the_app(): void
    {
        User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create(['phone' => '08031234567']);

        $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => 'x'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_refresh_rotates_and_a_bad_token_ends_the_session(): void
    {
        $session = $this->signIn();

        $pair = $this->postJson('api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']])
            ->json('data');

        $this->assertNotSame($session['refresh_token'], $pair['refresh_token']);

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => 'nonsense'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_SESSION_EXPIRED');
    }

    public function test_session_returns_the_account_for_the_token(): void
    {
        $session = $this->signIn();

        $this->withToken($session['access_token'])
            ->getJson('api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('data.access_token', $session['access_token'])
            ->assertJsonPath('data.refresh_token', '')
            ->assertJsonPath('data.user.phone_number', '08031234567');
    }

    public function test_session_needs_a_token_and_an_app_role(): void
    {
        $this->getJson('api/v1/auth/session')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs($admin)
            ->getJson('api/v1/auth/session')
            ->assertForbidden();
    }

    public function test_logout_ends_the_session(): void
    {
        $session = $this->signIn();

        $this->postJson('api/v1/auth/logout', ['refresh_token' => $session['refresh_token']])
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->withToken($session['access_token'])
            ->getJson('api/v1/auth/session')
            ->assertUnauthorized();

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized();
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function signIn(): array
    {
        User::factory()
            ->app()
            ->create(['phone' => '08031234567']);

        return $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => self::INSTALL])
            ->assertOk()
            ->json('data');
    }
}

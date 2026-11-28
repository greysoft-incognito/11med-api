<?php

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    private User $patient;

    private Google2FA $totp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totp = new Google2FA;
        $this->patient = User::factory()
            ->app()
            ->create(['phone' => '08031234567']);
    }

    private function login(): TestResponse
    {
        return $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => fake()->uuid()]);
    }

    /**
     * Turns two-step verification on for the patient and returns the secret and recovery codes.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function enable(User $user, string $prefix = 'api/v1/auth'): array
    {
        $secret = $this->actingAs($user, 'sanctum')
            ->postJson("{$prefix}/two-factor", ['password' => 'Password1'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_url']])
            ->json('data.secret');

        $codes = $this->postJson("{$prefix}/two-factor/confirm", ['code' => $this->totp->getCurrentOtp($secret)])
            ->assertOk()
            ->assertJsonCount(10, 'data.recovery_codes')
            ->json('data.recovery_codes');

        $this->app['auth']->forgetGuards();

        // The authenticator's clock is real time; forget the step confirm spent so the same code works once more.
        $user->forceFill(['two_factor_last_step' => null])->save();

        return [$secret, $codes];
    }

    public function test_setup_gives_a_secret_and_a_first_code_turns_it_on(): void
    {
        $this->actingAs($this->patient, 'sanctum')
            ->getJson('api/v1/auth/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.required', false);

        $url = $this->postJson('api/v1/auth/two-factor', ['password' => 'Password1'])->json('data.otpauth_url');

        $this->assertStringStartsWith('otpauth://totp/11med:', $url);

        $this->postJson('api/v1/auth/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CODE_INVALID')
            ->assertJsonPath('error.field', 'code');

        $this->patient->refresh();
        $this->assertNull($this->patient->two_factor_confirmed_at);
        $this->assertNotSame($this->patient->getRawOriginal('two_factor_secret'), $this->patient->two_factor_secret);

        $this->enable($this->patient->refresh());

        $this->actingAs($this->patient->refresh(), 'sanctum')
            ->getJson('api/v1/auth/two-factor')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.recovery_codes_left', 10);
    }

    public function test_setup_needs_the_password_and_cannot_run_twice(): void
    {
        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/two-factor', ['password' => 'Wrong1234'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT')
            ->assertJsonPath('error.field', 'password');

        $this->postJson('api/v1/auth/two-factor/confirm', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TWO_FACTOR_NOT_STARTED');

        $this->enable($this->patient);

        $this->actingAs($this->patient->refresh(), 'sanctum')
            ->postJson('api/v1/auth/two-factor', ['password' => 'Password1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'TWO_FACTOR_ALREADY_ENABLED');
    }

    public function test_sign_in_asks_for_the_second_step_and_a_code_completes_it(): void
    {
        [$secret] = $this->enable($this->patient);

        $challenge = $this->login()
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TWO_FACTOR_REQUIRED')
            ->assertJsonPath('error.meta.expires_in', 300)
            ->json('error.meta.challenge_token');

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => '000000'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CODE_INVALID');

        $code = $this->totp->getCurrentOtp($secret);

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => $code])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'user' => ['id']]]);

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');

        $again = $this->login()->json('error.meta.challenge_token');

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $again, 'code' => $code])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CODE_INVALID');
    }

    public function test_a_recovery_code_signs_in_once(): void
    {
        [, $codes] = $this->enable($this->patient);

        $challenge = $this->login()->json('error.meta.challenge_token');

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => strtoupper($codes[0])])->assertOk();

        $challenge = $this->login()->json('error.meta.challenge_token');

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => $codes[0]])->assertStatus(400);

        $this->assertCount(9, $this->patient->refresh()->two_factor_recovery_codes);
    }

    public function test_five_wrong_codes_end_the_challenge_and_it_lapses(): void
    {
        [$secret] = $this->enable($this->patient);

        $challenge = $this->login()->json('error.meta.challenge_token');

        foreach (range(1, 5) as $ignored) {
            $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => '000000'])->assertStatus(400);
        }

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => $this->totp->getCurrentOtp($secret)])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');

        $late = $this->login()->json('error.meta.challenge_token');
        $this->travel(6)->minutes();

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $late, 'code' => $this->totp->getCurrentOtp($secret)])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');
    }

    public function test_an_app_challenge_does_not_open_a_portal_session(): void
    {
        [$secret] = $this->enable($this->patient);

        $challenge = $this->login()->json('error.meta.challenge_token');

        $this->postJson('api/v1/portal/auth/two-factor', ['challenge_token' => $challenge, 'code' => $this->totp->getCurrentOtp($secret)])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');
    }

    public function test_turning_it_off_needs_the_password_and_a_code(): void
    {
        [$secret, $codes] = $this->enable($this->patient);

        $this->actingAs($this->patient->refresh(), 'sanctum')
            ->deleteJson('api/v1/auth/two-factor', ['password' => 'Password1', 'code' => '000000'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CODE_INVALID');

        $this->deleteJson('api/v1/auth/two-factor', ['password' => 'Wrong1234', 'code' => $codes[1]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT');

        $this->deleteJson('api/v1/auth/two-factor', ['password' => 'Password1', 'code' => $this->totp->getCurrentOtp($secret)])->assertOk();

        $this->deleteJson('api/v1/auth/two-factor', ['password' => 'Password1', 'code' => $codes[1]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TWO_FACTOR_NOT_ENABLED');

        $this->login()->assertOk();
    }

    public function test_new_recovery_codes_replace_the_old_ones(): void
    {
        [, $codes] = $this->enable($this->patient);

        $fresh = $this->actingAs($this->patient->refresh(), 'sanctum')
            ->postJson('api/v1/auth/two-factor/recovery-codes', ['password' => 'Password1'])
            ->assertOk()
            ->json('data.recovery_codes');

        $this->assertNotEquals($codes, $fresh);

        $challenge = $this->login()->json('error.meta.challenge_token');

        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => $codes[0]])->assertStatus(400);
        $this->postJson('api/v1/auth/two-factor/verify', ['challenge_token' => $challenge, 'code' => $fresh[0]])->assertOk();
    }

    public function test_a_portal_account_signs_in_with_the_second_step(): void
    {
        $desk = User::factory()
            ->portal(Role::Pharmacy)
            ->create(['email' => 'desk@pharmacy.ng']);

        [$secret] = $this->enable($desk, 'api/v1/portal/account');

        $challenge = $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@pharmacy.ng', 'password' => 'Password1'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TWO_FACTOR_REQUIRED')
            ->json('error.meta.challenge_token');

        $this->postJson('api/v1/portal/auth/two-factor', ['challenge_token' => $challenge, 'code' => $this->totp->getCurrentOtp($secret)])
            ->assertCreated()
            ->assertJsonPath('data.account.email', 'desk@pharmacy.ng')
            ->assertJsonPath('data.account.two_factor_enabled', true)
            ->assertJsonPath('data.account.two_factor_required', false);
    }

    public function test_an_admin_without_it_is_kept_off_the_desk_while_required(): void
    {
        config(['elevenmed.two_factor.admin_required' => true]);

        $admin = User::factory()
            ->portal(Role::Admin)
            ->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('api/v1/portal/admin/summary')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TWO_FACTOR_SETUP_REQUIRED');

        $this->getJson('api/v1/portal/auth/session')
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonPath('data.two_factor_enabled', false);

        $this->getJson('api/v1/portal/account/two-factor')
            ->assertOk()
            ->assertJsonPath('data.required', true);

        $this->enable($admin, 'api/v1/portal/account');

        $this->actingAs($admin->refresh(), 'sanctum')
            ->getJson('api/v1/portal/admin/summary')
            ->assertOk();
    }

    public function test_the_requirement_can_be_turned_off(): void
    {
        config(['elevenmed.two_factor.admin_required' => false]);

        $admin = User::factory()
            ->portal(Role::Admin)
            ->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('api/v1/portal/admin/summary')
            ->assertOk();
    }

    public function test_each_client_has_its_own_routes(): void
    {
        $this->getJson('api/v1/auth/two-factor')->assertUnauthorized();

        $desk = User::factory()
            ->portal(Role::Laboratory)
            ->create();

        $this->actingAs($desk, 'sanctum')
            ->getJson('api/v1/auth/two-factor')
            ->assertForbidden();

        $this->actingAs($this->patient, 'sanctum')
            ->getJson('api/v1/portal/account/two-factor')
            ->assertForbidden();
    }
}

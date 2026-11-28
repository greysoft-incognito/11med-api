<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountKind;
use App\Models\User;
use App\Notifications\Identity\PhoneCode;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhoneChangeTest extends TestCase
{
    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['elevenmed.otp.fixed_code' => '123456']);

        $this->patient = User::factory()
            ->app()
            ->create(['phone' => '08031234567']);
    }

    public function test_a_code_to_the_new_number_moves_the_account_and_frees_the_old_one(): void
    {
        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/phone', ['phone_number' => '+2348051112222', 'password' => 'Password1'])
            ->assertOk()
            ->assertJsonPath('data.otp_expires_in', 600)
            ->assertJsonPath('data.resends_remaining', 2);

        Notification::assertSentOnDemand(PhoneCode::class, fn($n, $channels, $notifiable) => in_array('08051112222', $notifiable->routes, true) || in_array('+2348051112222', $notifiable->routes, true));

        $this->postJson('api/v1/auth/phone/verify', ['code' => '654321'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'AUTH_INVALID_OTP');

        $this->postJson('api/v1/auth/phone/verify', ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.phone_number', '08051112222');

        $this->postJson('api/v1/auth/login', ['phone_number' => '08051112222', 'password' => 'Password1', 'device_id' => 'x'])->assertOk();
        $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => 'x'])->assertUnauthorized();

        $this->postJson('api/v1/auth/register', [
            'full_name' => 'New Holder',
            'phone_number' => '08031234567',
            'password' => 'Password1',
            'role' => AccountKind::Patient->value,
            'device_id' => 'x',
        ])->assertCreated();
    }

    public function test_the_password_is_the_proof(): void
    {
        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/phone', ['phone_number' => '08051112222', 'password' => 'Wrong1234'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT')
            ->assertJsonPath('error.field', 'password');

        Notification::assertNothingSent();
    }

    public function test_the_same_number_or_a_taken_one_is_refused(): void
    {
        User::factory()
            ->app()
            ->create(['phone' => '08059998888']);

        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/phone', ['phone_number' => '+2348031234567', 'password' => 'Password1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PHONE_UNCHANGED');

        $this->postJson('api/v1/auth/phone', ['phone_number' => '08059998888', 'password' => 'Password1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PHONE_ALREADY_REGISTERED')
            ->assertJsonPath('error.field', 'phone_number');

        $this->postJson('api/v1/auth/phone', ['phone_number' => '0805', 'password' => 'Password1'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_a_number_an_abandoned_sign_up_holds_can_be_taken(): void
    {
        $abandoned = User::factory()
            ->app()
            ->create(['phone' => '08057776666', 'phone_verified_at' => null]);

        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/phone', ['phone_number' => '08057776666', 'password' => 'Password1'])
            ->assertOk();

        $this->postJson('api/v1/auth/phone/verify', ['code' => '123456'])->assertOk();

        $this->assertNull(User::withTrashed()->find($abandoned->id));
    }

    public function test_verify_without_a_request_and_the_code_budget(): void
    {
        $this->actingAs($this->patient, 'sanctum')
            ->postJson('api/v1/auth/phone/verify', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PHONE_CHANGE_NOT_STARTED');

        foreach (['08051110001', '08051110002', '08051110003'] as $number) {
            $this->postJson('api/v1/auth/phone', ['phone_number' => $number, 'password' => 'Password1'])->assertOk();
        }

        $this->postJson('api/v1/auth/phone', ['phone_number' => '08051110004', 'password' => 'Password1'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_OTP_RESEND_LIMIT');
    }

    public function test_only_app_accounts_change_a_phone(): void
    {
        $this->postJson('api/v1/auth/phone', ['phone_number' => '08051112222', 'password' => 'Password1'])->assertUnauthorized();

        $desk = User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create();

        $this->actingAs($desk, 'sanctum')
            ->postJson('api/v1/auth/phone', ['phone_number' => '08051112222', 'password' => 'Password1'])
            ->assertForbidden();
    }
}

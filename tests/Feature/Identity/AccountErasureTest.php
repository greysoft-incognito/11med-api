<?php

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Events\Identity\AccountErasing;
use App\Models\AccountErasure;
use App\Models\ConsentRecord;
use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountErasureTest extends TestCase
{
    private const INSTALL = '0b7f0f0e-5d6a-4c43-9d1e-2f4a8f0c9a11';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['elevenmed.otp.fixed_code' => '123456']);
    }

    /**
     * @return array{access_token: string, refresh_token: string, user: array<string, mixed>}
     */
    private function account(): array
    {
        $this->postJson('api/v1/auth/register', [
            'full_name' => 'Amaka Obi',
            'phone_number' => '08031234567',
            'password' => 'Password1',
            'role' => 'patient',
            'device_id' => self::INSTALL,
        ])->assertCreated();

        $session = $this->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '123456'])
            ->json('data');

        $this->withToken($session['access_token'])
            ->putJson('api/v1/devices/' . self::INSTALL, [
                'platform' => 'android',
                'push_provider' => 'fcm',
                'push_token' => 't',
                'app_version' => '1.1.0',
                'locale' => 'en',
            ])
            ->assertOk();

        $user = User::firstWhere('phone', '08031234567');

        SocialIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'subject' => 's1', 'confirmed_at' => now()]);

        return $session;
    }

    public function test_erasure_removes_the_person_and_keeps_only_the_severed_records(): void
    {
        $session = $this->account();
        $id = User::firstWhere('phone', '08031234567')->id;

        $this->withToken($session['access_token'])
            ->deleteJson('api/v1/auth/account', ['password' => 'Password1', 'reason' => 'Moving abroad'])
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertNull(User::withTrashed()->find($id));
        $this->assertSame(0, Device::withTrashed()->count());
        $this->assertSame(0, SocialIdentity::withTrashed()->count());
        $this->assertSame(0, RefreshToken::where('user_id', $id)->count());

        $consent = ConsentRecord::sole();
        $this->assertNull($consent->user_id);
        $this->assertSame('1.1', $consent->policy_version);

        $erasure = AccountErasure::sole();
        $this->assertSame('patient', $erasure->role);
        $this->assertSame('Moving abroad', $erasure->reason);

        $this->app['auth']->forgetGuards();

        $this->withToken($session['access_token'])
            ->getJson('api/v1/auth/session')
            ->assertUnauthorized();

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized();
    }

    public function test_the_phone_number_is_free_again(): void
    {
        $session = $this->account();

        $this->withToken($session['access_token'])
            ->deleteJson('api/v1/auth/account', ['password' => 'Password1'])
            ->assertOk();

        $this->postJson('api/v1/auth/register', [
            'full_name' => 'Next Owner',
            'phone_number' => '08031234567',
            'password' => 'Password1',
            'role' => 'patient',
            'device_id' => self::INSTALL,
        ])->assertCreated();
    }

    public function test_modules_are_told_inside_the_erasure(): void
    {
        Event::fake([AccountErasing::class]);

        $session = $this->account();

        $this->withToken($session['access_token'])
            ->deleteJson('api/v1/auth/account', ['password' => 'Password1'])
            ->assertOk();

        Event::assertDispatched(AccountErasing::class, fn(AccountErasing $e) => $e->user->phone === '08031234567');
    }

    public function test_the_password_confirms_it(): void
    {
        $session = $this->account();

        $this->withToken($session['access_token'])
            ->deleteJson('api/v1/auth/account', ['password' => 'Wrong1234'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT')
            ->assertJsonPath('error.field', 'password');

        $this->withToken($session['access_token'])
            ->deleteJson('api/v1/auth/account', [])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'password');

        $this->assertNotNull(User::firstWhere('phone', '08031234567'));
    }

    public function test_erasure_needs_an_app_account(): void
    {
        $this->deleteJson('api/v1/auth/account', ['password' => 'Password1'])->assertUnauthorized();

        $lab = User::factory()
            ->portal(Role::Laboratory)
            ->create();

        $this->actingAs($lab)
            ->deleteJson('api/v1/auth/account', ['password' => 'Password1'])
            ->assertForbidden();
    }
}

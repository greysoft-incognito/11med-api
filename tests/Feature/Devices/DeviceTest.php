<?php

namespace Tests\Feature\Devices;

use App\Enums\Role;
use App\Models\Device;
use App\Models\User;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    private const INSTALL = '0b7f0f0e-5d6a-4c43-9d1e-2f4a8f0c9a11';

    private const BODY = [
        'platform' => 'ios',
        'push_provider' => 'apns',
        'push_token' => 'a1b2c3',
        'app_version' => '1.1.0',
        'locale' => 'en',
    ];

    public function test_registering_is_an_idempotent_upsert(): void
    {
        $user = User::factory()
            ->app()
            ->create();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($user)
                ->putJson('api/v1/devices/' . self::INSTALL, self::BODY)
                ->assertOk()
                ->assertJsonPath('data.install_id', self::INSTALL)
                ->assertJsonPath('data.platform', 'ios')
                ->assertJsonPath('data.push_provider', 'apns')
                ->assertJsonStructure(['data' => ['registered_at']]);
        }

        $this->assertSame(1, Device::count());
    }

    public function test_an_install_moves_to_whoever_registers_it_last(): void
    {
        [$first, $second] = User::factory(2)
            ->app()
            ->create();

        $this->actingAs($first)->putJson('api/v1/devices/' . self::INSTALL, self::BODY)->assertOk();
        $this->actingAs($second)->putJson('api/v1/devices/' . self::INSTALL, ['push_token' => 'd4e5'] + self::BODY)->assertOk();

        $device = Device::sole();

        $this->assertSame($second->id, $device->user_id);
        $this->assertSame('d4e5', $device->push_token);
    }

    public function test_only_the_holder_removes_a_device_and_anyone_else_is_a_silent_no_op(): void
    {
        [$holder, $stranger] = User::factory(2)
            ->app()
            ->create();

        $this->actingAs($holder)->putJson('api/v1/devices/' . self::INSTALL, self::BODY)->assertOk();

        $this->actingAs($stranger)
            ->deleteJson('api/v1/devices/' . self::INSTALL)
            ->assertOk()
            ->assertJsonPath('data.install_id', self::INSTALL);

        $this->assertSame(1, Device::count());

        $this->actingAs($holder)
            ->deleteJson('api/v1/devices/' . self::INSTALL)
            ->assertOk();

        $this->assertSame(0, Device::count());
    }

    public function test_a_session_ended_on_the_server_drops_its_device(): void
    {
        $user = User::factory()
            ->app()
            ->create(['phone' => '08031234567']);

        $session = $this->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => self::INSTALL])
            ->json('data');

        $this->withToken($session['access_token'])->putJson('api/v1/devices/' . self::INSTALL, self::BODY)->assertOk();

        $this->postJson('api/v1/auth/logout', ['refresh_token' => $session['refresh_token']])->assertOk();

        $this->assertSame(0, Device::where('user_id', $user->id)->count());
    }

    public function test_the_body_and_install_id_are_validated(): void
    {
        $user = User::factory()
            ->app()
            ->create();

        $this->actingAs($user)
            ->putJson('api/v1/devices/' . self::INSTALL, ['push_provider' => 'expo'] + self::BODY)
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'push_provider');

        $this->actingAs($user)
            ->putJson('api/v1/devices/not-a-uuid', self::BODY)
            ->assertNotFound();
    }

    public function test_devices_need_an_app_account(): void
    {
        $this->putJson('api/v1/devices/' . self::INSTALL, self::BODY)->assertUnauthorized();

        $admin = User::factory()
            ->portal(Role::Admin)
            ->create();

        $this->actingAs($admin)
            ->putJson('api/v1/devices/' . self::INSTALL, self::BODY)
            ->assertForbidden();
    }
}

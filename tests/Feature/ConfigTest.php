<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithConsole;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    use InteractsWithConsole;

    public function test_can_load_config(): void
    {
        $user = User::first();

        $this->artisan('app:sync-roles');
        $user->syncRoles(config('permission-defs.roles', []));
        $user->syncPermissions(config('permission-defs.permissions', []));

        Sanctum::actingAs(
            $user,
            ['*']
        );

        $response = $this->get('/api/admin/configurations');

        $response->assertStatus(200);
    }

    public function test_can_load_a_particular_config(): void
    {
        $user = User::first();

        $this->artisan('app:sync-roles');
        $user->syncRoles(config('permission-defs.roles', []));
        $user->syncPermissions(config('permission-defs.permissions', []));

        Sanctum::actingAs(
            $user,
            ['*']
        );

        $response = $this->get('/api/admin/configurations/app_name');

        $response->assertStatus(200);
    }

    public function test_can_save_config(): void
    {
        $user = User::first();

        $this->artisan('app:sync-roles');
        $user->syncRoles(config('permission-defs.roles', []));
        $user->syncPermissions(config('permission-defs.permissions', []));

        Sanctum::actingAs(
            $user,
            ['*']
        );

        $response = $this->post('/api/admin/configurations', [
            'app_name' => 'Test App',
        ]);

        $response->assertStatus(202);
    }
}

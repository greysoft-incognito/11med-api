<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountKind;
use App\Models\User;
use Tests\TestCase;

class KitRoutesTest extends TestCase
{
    public function test_the_kits_sign_up_and_account_routes_are_gone(): void
    {
        $this->postJson('api/auth/register')->assertNotFound();
        $this->postJson('api/auth/login')->assertNotFound();
        $this->deleteJson('api/account/delete')->assertNotFound();
    }

    public function test_only_the_team_reaches_the_kits_admin_routes(): void
    {
        foreach ([AccountKind::Patient, AccountKind::Doctor, AccountKind::Pharmacy, AccountKind::Laboratory] as $role) {
            $user = User::factory()
                ->app($role)
                ->create();

            $this->actingAs($user)
                ->getJson('api/admin/users')
                ->assertForbidden();
        }

        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs($admin)
            ->getJson('api/admin/users')
            ->assertOk();
    }
}

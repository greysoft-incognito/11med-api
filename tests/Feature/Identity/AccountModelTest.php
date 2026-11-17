<?php

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

class AccountModelTest extends TestCase
{
    public function test_every_role_exists_and_only_admin_holds_the_kit_permissions(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()
                ->app($role)
                ->create();

            $this->assertSame($role, $user->role());
            $this->assertSame($role === Role::Admin, $user->can('manage-users'));
        }
    }

    public function test_a_user_gets_a_public_uuid(): void
    {
        $user = User::factory()
            ->app()
            ->create();

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $user->uuid);
        $this->assertSame('uuid', $user->getRouteKeyName());
    }

    public function test_a_soft_deleted_account_frees_its_phone_and_email(): void
    {
        $first = User::factory()
            ->app()
            ->create(['email' => 'ada@example.com']);

        $first->delete();

        $second = User::factory()
            ->app()
            ->create(['phone' => $first->phone, 'email' => 'ADA@example.com']);

        $this->assertNotNull($second->id);
    }

    public function test_two_live_accounts_cannot_share_a_phone(): void
    {
        $first = User::factory()
            ->app()
            ->create();

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()
            ->app()
            ->create(['phone' => $first->phone]);
    }
}

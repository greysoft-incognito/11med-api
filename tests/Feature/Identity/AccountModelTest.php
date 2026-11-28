<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountKind;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

class AccountModelTest extends TestCase
{
    public function test_an_account_holds_any_kinds_and_only_a_super_admin_holds_the_kit_permissions(): void
    {
        $user = User::factory()
            ->app(AccountKind::Patient)
            ->holding(AccountKind::Doctor)
            ->holding(AccountKind::Pharmacy)
            ->create();

        $this->assertSame([AccountKind::Patient, AccountKind::Doctor, AccountKind::Pharmacy], $user->kinds());
        $this->assertTrue($user->holds(AccountKind::Laboratory, AccountKind::Doctor));
        $this->assertFalse($user->holds(AccountKind::Laboratory));
        $this->assertFalse($user->isStaff());
        $this->assertFalse($user->can('manage-users'));

        $admin = User::factory()
            ->admin()
            ->create();

        $this->assertSame([], $admin->kinds());
        $this->assertTrue($admin->isStaff());
        $this->assertTrue($admin->can('manage-users'));
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

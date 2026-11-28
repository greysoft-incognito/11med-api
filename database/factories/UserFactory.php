<?php

namespace Database\Factories;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'lastname' => fake()->lastName,
            'password' => static::$password ??= Hash::make('password'),
            'firstname' => fake()->firstName,
            'remember_token' => Str::random(10),
            'email_verified_at' => now(),
            'username' => fake()->userName(),
        ];
    }

    /**
     * An app account: a verified Nigerian phone, no email, password `Password1`.
     */
    public function app(AccountKind $kind = AccountKind::Patient): static
    {
        return $this
            ->state(fn() => [
                'full_name' => fake()->name(),
                'phone' => '080' . fake()->unique()->numerify('########'),
                'phone_verified_at' => now(),
                'email' => null,
                'email_verified_at' => null,
                'password' => Hash::make('Password1'),
            ])
            ->holding($kind);
    }

    /**
     * A partner portal account: an email, no phone, password `Password1`.
     */
    public function portal(AccountKind $kind = AccountKind::Pharmacy): static
    {
        return $this
            ->state(fn() => [
                'full_name' => fake()->name(),
                'phone' => null,
                'password' => Hash::make('Password1'),
            ])
            ->holding($kind);
    }

    /**
     * A team account holding only the given admin permissions, directly.
     *
     * @param  list<AdminPermission>  $permissions
     */
    public function staff(array $permissions = []): static
    {
        return $this
            ->state(fn() => [
                'full_name' => fake()->name(),
                'phone' => null,
                'password' => Hash::make('Password1'),
                'staff_since' => now(),
            ])
            ->afterCreating(fn(User $user) => $user->givePermissionTo(AdminPermission::values($permissions)));
    }

    /**
     * A super admin: staff access holding the super admin role.
     */
    public function admin(): static
    {
        return $this
            ->staff()
            ->afterCreating(fn(User $user) => $user->assignRole(StaffRole::superAdminName()));
    }

    /**
     * Also holds this account kind.
     */
    public function holding(AccountKind $kind): static
    {
        return $this->afterCreating(fn(User $user) => $user->grantKind($kind));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\Role;
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
    public function app(Role $role = Role::Patient): static
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
            ->afterCreating(fn(User $user) => $user->assignRole($role->value));
    }

    /**
     * A portal account: an email, no phone, password `Password1`.
     */
    public function portal(Role $role = Role::Admin): static
    {
        return $this
            ->state(fn() => [
                'full_name' => fake()->name(),
                'phone' => null,
                'password' => Hash::make('Password1'),
            ])
            ->afterCreating(fn(User $user) => $user->assignRole($role->value));
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

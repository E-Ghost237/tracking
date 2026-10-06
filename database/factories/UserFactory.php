<?php

namespace Database\Factories;

use App\Models\Role;
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Attach a role by slug after the user is created.
     */
    public function withRole(string $slug): static
    {
        return $this->afterCreating(function (User $user) use ($slug): void {
            $user->roles()->attach(Role::query()->where('slug', $slug)->value('id'));
            $user->flushPermissionCache();
        });
    }

    /**
     * A customer account (the role every self-registered user gets).
     */
    public function customer(): static
    {
        return $this->withRole('customer');
    }

    /**
     * Staff member with two-factor authentication confirmed.
     */
    public function staff(string $role, string $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->withRole($role);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

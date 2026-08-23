<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'               => fake()->name(),
            'email'              => fake()->unique()->safeEmail(),
            'phone'              => '+60 1' . fake()->numerify('# ### ####'),
            'role'               => UserRole::OWNER,
            'is_super_admin'     => false,
            'email_verified_at'  => now(),
            'password'           => static::$password ??= Hash::make('password'),
            'remember_token'     => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => UserRole::OWNER]);
    }

    public function tenant(): static
    {
        return $this->state(fn () => ['role' => UserRole::TENANT, 'status' => 'active']);
    }

    public function invitedTenant(): static
    {
        return $this->state(fn () => [
            'role'       => UserRole::TENANT,
            'status'     => 'invited',
            'invited_at' => now(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN, 'is_super_admin' => false]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN, 'is_super_admin' => true]);
    }

    public function suspended(string $reason = 'Unpaid subscription'): static
    {
        return $this->state(fn () => ['suspended_at' => now(), 'suspension_reason' => $reason]);
    }
}

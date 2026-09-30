<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;

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
     * No role and no tenant by default: both are set by trusted paths in the application, so a
     * test that needs a functioning member of a business has to say which business and which
     * role. A user with neither has no permissions at all, which is the correct default.
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
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A member of the given business, holding the given role.
     */
    public function memberOf(Tenant $tenant, Role $role = Role::Owner): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->getKey(),
            'role' => $role,
        ]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    /**
     * GroomerLoop staff: a platform user belonging to no grooming business.
     */
    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => Role::PlatformAdmin,
        ]);
    }
}

<?php

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Role;
use Modules\Identity\Models\Invitation;

/**
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::FrontDesk,
            'token_hash' => Invitation::hashToken(Str::random(64)),
            'expires_at' => now()->addDays(Invitation::LIFETIME_DAYS),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): self
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }

    public function forRole(Role $role): self
    {
        return $this->state(fn () => ['role' => $role]);
    }
}

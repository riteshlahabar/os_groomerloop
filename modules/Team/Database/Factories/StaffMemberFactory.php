<?php

namespace Modules\Team\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Team\Domain\StaffStatus;
use Modules\Team\Models\StaffMember;

/**
 * @extends Factory<StaffMember>
 */
final class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

    /**
     * No user_id by default. A staff record with no login is a real, bookable groomer (`D-018`),
     * not an incomplete one — tests that need the linked case use linkedToUser().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->name(),
            'job_title' => fake()->randomElement(['Groomer', 'Senior Groomer', 'Bather', 'Trainee']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'status' => StaffStatus::Active,
            'is_bookable_online' => true,
            'position' => 0,
        ];
    }

    public function named(string $name): self
    {
        return $this->state(fn (): array => ['display_name' => $name]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['status' => StaffStatus::Inactive]);
    }

    public function notBookableOnline(): self
    {
        return $this->state(fn (): array => ['is_bookable_online' => false]);
    }

    /**
     * Links the staff record to an existing account. Not the default: most tests exercise the
     * common case of a staff record standing on its own.
     */
    public function linkedToUser(int $userId): self
    {
        return $this->state(fn (): array => ['user_id' => $userId]);
    }
}

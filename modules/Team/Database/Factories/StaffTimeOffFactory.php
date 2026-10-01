<?php

namespace Modules\Team\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Team\Models\StaffMember;
use Modules\Team\Models\StaffTimeOff;

/**
 * @extends Factory<StaffTimeOff>
 */
final class StaffTimeOffFactory extends Factory
{
    protected $model = StaffTimeOff::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 weeks');

        return [
            'staff_member_id' => StaffMember::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+1 day'),
            'is_all_day' => true,
            'reason' => null,
        ];
    }

    public function allDay(): self
    {
        return $this->state(fn (): array => ['is_all_day' => true]);
    }

    public function between(string $startsAt, string $endsAt): self
    {
        return $this->state(fn (): array => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_all_day' => false,
        ]);
    }
}

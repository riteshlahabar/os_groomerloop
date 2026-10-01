<?php

namespace Modules\Team\Database\Factories;

use App\Domain\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Team\Models\StaffMember;
use Modules\Team\Models\StaffWorkingHour;

/**
 * @extends Factory<StaffWorkingHour>
 */
final class StaffWorkingHourFactory extends Factory
{
    protected $model = StaffWorkingHour::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMember::factory(),
            'day_of_week' => DayOfWeek::Monday,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ];
    }

    public function onDay(DayOfWeek $day): self
    {
        return $this->state(fn (): array => ['day_of_week' => $day]);
    }

    public function from(string $startsAt, string $endsAt): self
    {
        return $this->state(fn (): array => ['starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }
}

<?php

namespace Modules\Scheduling\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Models\Appointment;

/**
 * @extends Factory<Appointment>
 */
final class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * No defaults for customer_id/pet_id/service_id: all three are required, cross-module
     * foreign keys with no sensible fake value, the same reasoning `PetFactory` uses for
     * customer_id. Every test says explicitly who and what the appointment is for.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::parse(fake()->dateTimeBetween('+1 day', '+30 days'))->setTime(10, 0);

        return [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(60),
        ];
    }

    public function forCustomer(int $customerId): self
    {
        return $this->state(fn (): array => ['customer_id' => $customerId]);
    }

    public function forPet(int $petId): self
    {
        return $this->state(fn (): array => ['pet_id' => $petId]);
    }

    public function forService(int $serviceId): self
    {
        return $this->state(fn (): array => ['service_id' => $serviceId]);
    }

    public function forStaffMember(?int $staffMemberId): self
    {
        return $this->state(fn (): array => ['staff_member_id' => $staffMemberId]);
    }

    public function startingAt(Carbon $start, int $minutes = 60): self
    {
        return $this->state(fn (): array => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($minutes),
        ]);
    }

    public function withStatus(AppointmentStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function cancelled(): self
    {
        return $this->withStatus(AppointmentStatus::Cancelled);
    }

    public function completed(): self
    {
        return $this->withStatus(AppointmentStatus::Completed);
    }
}

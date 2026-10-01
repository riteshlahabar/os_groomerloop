<?php

namespace Modules\Scheduling\Tests\Feature;

use App\Domain\DayOfWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\Service;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Models\Pet;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Models\BusinessHour;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

final class AppointmentRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private StaffMember $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();
        app(TenantContext::class)->set($this->tenant);

        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();
        $this->staff = StaffMember::factory()->create();
        $this->staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    }

    public function test_an_appointment_can_be_rescheduled_to_a_free_slot(): void
    {
        $appointment = $this->bookedAppointment();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/reschedule", [
                'starts_at' => '2026-10-05 13:00:00',
            ])
            ->assertOk()
            ->assertJsonPath('data.starts_at', '2026-10-05T13:00:00+00:00');
    }

    /**
     * Rescheduling to the exact time an appointment is already at must not refuse itself as a
     * conflict — the availability re-check excludes the appointment's own current slot.
     */
    public function test_rescheduling_to_the_same_time_it_already_occupies_succeeds(): void
    {
        $appointment = $this->bookedAppointment();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/reschedule", [
                'starts_at' => $appointment->starts_at->toIso8601String(),
            ])
            ->assertOk();
    }

    public function test_rescheduling_onto_another_appointments_slot_is_refused(): void
    {
        $appointment = $this->bookedAppointment();
        $this->bookedAppointmentAt('2026-10-05 13:00:00');

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/reschedule", [
                'starts_at' => '2026-10-05 13:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_a_completed_appointment_cannot_be_rescheduled(): void
    {
        $appointment = $this->bookedAppointment();
        $appointment->update(['status' => 'confirmed']);
        $appointment->update(['status' => 'checked-in']);
        $appointment->update(['status' => 'in-service']);
        $appointment->update(['status' => 'completed']);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/reschedule", [
                'starts_at' => '2026-10-05 13:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_a_cancelled_appointment_cannot_be_rescheduled(): void
    {
        $appointment = $this->bookedAppointment();
        $appointment->update(['status' => 'cancelled']);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/reschedule", [
                'starts_at' => '2026-10-05 13:00:00',
            ])
            ->assertUnprocessable();
    }

    private function bookedAppointment(): Appointment
    {
        return $this->bookedAppointmentAt('2026-10-05 10:00:00');
    }

    private function bookedAppointmentAt(string $start): Appointment
    {
        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();
        $service = Service::factory()->create();

        return Appointment::factory()
            ->forCustomer($customer->getKey())
            ->forPet($pet->getKey())
            ->forService($service->getKey())
            ->forStaffMember($this->staff->getKey())
            ->startingAt(Carbon::parse($start), 60)
            ->create();
    }
}

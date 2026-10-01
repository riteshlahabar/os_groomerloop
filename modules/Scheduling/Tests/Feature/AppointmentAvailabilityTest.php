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
use Modules\Scheduling\Services\AvailabilityEngine;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * "Can this appointment happen" (spec §11, invariant #2): business hours ∩ the service's own
 * rules ∩ staff availability ∩ no conflicting appointment, each layer proven to refuse
 * independently — both at the `AvailabilityEngine` level and over the `GET /availability`
 * endpoint it backs.
 */
final class AppointmentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_no_business_hours_at_all_refuses_every_slot(): void
    {
        $service = Service::factory()->create();

        $this->assertFalse($this->engine()->isAvailable(
            $service->getKey(), null, new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_a_slot_inside_business_hours_with_no_staff_requested_is_available(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $this->assertTrue($this->engine()->isAvailable(
            $service->getKey(), null, new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_a_day_the_business_is_closed_refuses_even_with_hours_set_on_other_days(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        // Tuesday — no row covers it.
        $this->assertFalse($this->engine()->isAvailable(
            $service->getKey(), null, new \DateTimeImmutable('2026-10-06 10:00:00')
        ));
    }

    public function test_the_services_own_availability_rules_are_consulted(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        // The service is only sold on Saturdays; the business is open Monday, but this service is not.
        $service->availabilityWindows()->create(['day_of_week' => 6, 'starts_at' => '09:00', 'ends_at' => '17:00']);

        $this->assertFalse($this->engine()->isAvailable(
            $service->getKey(), null, new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_a_staff_member_with_no_working_hours_is_unavailable_even_when_the_business_is_open(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();
        $staff = StaffMember::factory()->create();

        $this->assertFalse($this->engine()->isAvailable(
            $service->getKey(), $staff->getKey(), new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_a_staff_member_on_time_off_is_unavailable(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $staff = StaffMember::factory()->create();
        $staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $staff->timeOff()->create(['starts_at' => '2026-10-05 00:00:00', 'ends_at' => '2026-10-06 00:00:00']);

        $this->assertFalse($this->engine()->isAvailable(
            $service->getKey(), $staff->getKey(), new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_a_conflicting_appointment_refuses_the_slot(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $staff = StaffMember::factory()->create();
        $staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);

        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();

        Appointment::factory()
            ->forCustomer($customer->getKey())
            ->forPet($pet->getKey())
            ->forService($service->getKey())
            ->forStaffMember($staff->getKey())
            ->startingAt(Carbon::parse('2026-10-05 09:30:00'), 60)
            ->create();

        $this->assertFalse($this->engine()->isAvailable(
            $service->getKey(), $staff->getKey(), new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_a_cancelled_appointment_no_longer_occupies_its_slot(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $staff = StaffMember::factory()->create();
        $staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);

        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();

        Appointment::factory()
            ->forCustomer($customer->getKey())
            ->forPet($pet->getKey())
            ->forService($service->getKey())
            ->forStaffMember($staff->getKey())
            ->startingAt(Carbon::parse('2026-10-05 09:30:00'), 60)
            ->cancelled()
            ->create();

        $this->assertTrue($this->engine()->isAvailable(
            $service->getKey(), $staff->getKey(), new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_the_availability_endpoint_reflects_the_same_answer(): void
    {
        $service = Service::factory()->create();
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/availability?'.http_build_query([
                'service_id' => $service->getKey(),
                'starts_at' => '2026-10-05 10:00:00',
            ]))
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/availability?'.http_build_query([
                'service_id' => $service->getKey(),
                'starts_at' => '2026-10-06 10:00:00',
            ]))
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    private function engine(): AvailabilityEngine
    {
        return app(AvailabilityEngine::class);
    }
}

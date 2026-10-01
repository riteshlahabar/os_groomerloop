<?php

namespace Modules\Scheduling\Tests\Feature;

use App\Domain\DayOfWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

/**
 * Tenant isolation, proven through route model binding (D-014) — never only an explicit query —
 * plus the request-body paths: every cross-module id a booking request carries is validated
 * through that module's own contract, so another tenant's customer/pet/service/staff id is
 * refused the same way a price list in Catalog refuses one.
 */
final class AppointmentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $owner;

    private Appointment $strangersAppointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        $this->otherTenant = Tenant::factory()->create();

        $this->strangersAppointment = app(TenantContext::class)->runFor(
            $this->otherTenant,
            function (): Appointment {
                $customer = Customer::factory()->create();
                $pet = Pet::factory()->of($customer->getKey())->create();
                $service = Service::factory()->create();

                return Appointment::factory()
                    ->forCustomer($customer->getKey())
                    ->forPet($pet->getKey())
                    ->forService($service->getKey())
                    ->create();
            }
        );

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_another_tenants_appointment_cannot_be_shown(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/appointments/{$this->strangersAppointment->getKey()}")
            ->assertNotFound();
    }

    public function test_another_tenants_appointment_cannot_be_updated(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$this->strangersAppointment->getKey()}", [
                'customer_notes' => 'Hijacked',
            ])
            ->assertNotFound();
    }

    public function test_another_tenants_appointment_cannot_be_cancelled(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/appointments/{$this->strangersAppointment->getKey()}")
            ->assertNotFound();

        $this->assertNotSame('cancelled', $this->strangersAppointment->refresh()->status->value);
    }

    public function test_another_tenants_appointment_cannot_be_rescheduled(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$this->strangersAppointment->getKey()}/reschedule", [
                'starts_at' => '2026-10-05 13:00:00',
            ])
            ->assertNotFound();
    }

    public function test_another_tenants_appointment_cannot_have_its_status_changed(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$this->strangersAppointment->getKey()}/status", [
                'status' => 'confirmed',
            ])
            ->assertNotFound();
    }

    public function test_the_appointment_list_shows_only_this_tenants_appointments(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/appointments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // --- Cross-tenant ids inside a booking request body --------------------------------------

    public function test_another_tenants_customer_cannot_be_booked_against(): void
    {
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $strangersCustomer = app(TenantContext::class)->runFor(
            $this->otherTenant,
            fn (): Customer => Customer::factory()->create()
        );

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $strangersCustomer->getKey(),
                'pet_id' => Pet::factory()->of(Customer::factory()->create()->getKey())->create()->getKey(),
                'service_id' => Service::factory()->create()->getKey(),
                'starts_at' => '2026-10-05 10:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_another_tenants_pet_cannot_be_booked_against(): void
    {
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $customer = Customer::factory()->create();
        $strangersPet = app(TenantContext::class)->runFor(
            $this->otherTenant,
            fn (): Pet => Pet::factory()->of(Customer::factory()->create()->getKey())->create()
        );

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $customer->getKey(),
                'pet_id' => $strangersPet->getKey(),
                'service_id' => Service::factory()->create()->getKey(),
                'starts_at' => '2026-10-05 10:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pet_id');
    }

    public function test_another_tenants_service_cannot_be_booked(): void
    {
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();
        $strangersService = app(TenantContext::class)->runFor(
            $this->otherTenant,
            fn (): Service => Service::factory()->create()
        );

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $customer->getKey(),
                'pet_id' => $pet->getKey(),
                'service_id' => $strangersService->getKey(),
                'starts_at' => '2026-10-05 10:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_id');
    }

    public function test_another_tenants_staff_member_cannot_be_assigned(): void
    {
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();
        $service = Service::factory()->create();
        $strangersStaff = app(TenantContext::class)->runFor(
            $this->otherTenant,
            fn (): StaffMember => StaffMember::factory()->create()
        );

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $customer->getKey(),
                'pet_id' => $pet->getKey(),
                'service_id' => $service->getKey(),
                'staff_member_id' => $strangersStaff->getKey(),
                'starts_at' => '2026-10-05 10:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff_member_id');
    }
}

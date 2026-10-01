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
use Modules\Team\Actions\SyncStaffServices;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Booking an appointment over HTTP (spec §11). Every cross-module id in the request is validated
 * through its owning module's own contract, never a bare `exists:table,id` — this is where each
 * of those checks is proven independently.
 */
final class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Customer $customer;

    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();
        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->create();
        $this->pet = Pet::factory()->of($this->customer->getKey())->create();

        // Monday 09:00-17:00 — every booking test below targets a Monday inside this window
        // unless it is deliberately testing business hours refusing the slot.
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();
    }

    public function test_an_appointment_can_be_booked_with_no_staff_member(): void
    {
        $service = $this->service();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($service->getKey()))
            ->assertCreated()
            ->assertJsonPath('data.staff_member_id', null)
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.customer_name', $this->customer->first_name.' '.$this->customer->last_name);

        $this->assertDatabaseHas('appointments', [
            'customer_id' => $this->customer->getKey(),
            'pet_id' => $this->pet->getKey(),
            'service_id' => $service->getKey(),
            'staff_member_id' => null,
        ]);
    }

    public function test_an_appointment_can_be_booked_with_an_eligible_staff_member(): void
    {
        $service = $this->service();
        $staff = $this->availableStaff();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($service->getKey(), $staff->getKey()))
            ->assertCreated()
            ->assertJsonPath('data.staff_member_id', $staff->getKey())
            ->assertJsonPath('data.staff_member_name', $staff->display_name);
    }

    public function test_an_unknown_customer_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($this->service()->getKey(), customerId: 9_999_999))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_a_pet_not_belonging_to_the_named_customer_is_refused(): void
    {
        $otherCustomer = Customer::factory()->create();
        $strangersPet = Pet::factory()->of($otherCustomer->getKey())->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                ...$this->payload($this->service()->getKey()),
                'pet_id' => $strangersPet->getKey(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pet_id');
    }

    public function test_an_unsellable_service_is_refused(): void
    {
        $inactive = $this->service()->fresh();
        $inactive->update(['status' => 'inactive']);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($inactive->getKey()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_id');
    }

    public function test_an_unknown_staff_member_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($this->service()->getKey(), staffMemberId: 9_999_999))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff_member_id');
    }

    public function test_a_staff_member_not_eligible_for_the_service_is_refused(): void
    {
        $service = $this->service();
        $staff = $this->availableStaff();

        app(SyncStaffServices::class)->execute($staff, [
            Service::factory()->create()->getKey(),
        ]);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($service->getKey(), $staff->getKey()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff_member_id');
    }

    public function test_an_add_on_the_service_actually_offers_is_accepted(): void
    {
        $service = $this->service();
        $addOn = Service::factory()->addOn()->create();
        $service->addOns()->attach($addOn, ['tenant_id' => $this->tenant->getKey()]);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                ...$this->payload($service->getKey()),
                'add_on_service_ids' => [$addOn->getKey()],
            ])
            ->assertCreated()
            ->assertJsonPath('data.add_ons.0.service_id', $addOn->getKey());

        $this->assertDatabaseHas('appointment_addons', ['service_id' => $addOn->getKey()]);
    }

    /**
     * The validation this session added: nothing previously stopped a client attaching an add-on
     * the service was never offered with, including another tenant's service id.
     */
    public function test_an_add_on_the_service_does_not_offer_is_refused(): void
    {
        $service = $this->service();
        $notOffered = Service::factory()->addOn()->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                ...$this->payload($service->getKey()),
                'add_on_service_ids' => [$notOffered->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('add_on_service_ids');

        $this->assertDatabaseMissing('appointments', ['customer_id' => $this->customer->getKey()]);
    }

    public function test_booking_outside_business_hours_is_refused(): void
    {
        // Tuesday: no BusinessHour row covers it at all.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                ...$this->payload($this->service()->getKey()),
                'starts_at' => '2026-10-06 10:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    /**
     * The sequential proof of the availability check: a second booking attempt for the same
     * staff member at an overlapping time is refused once the first has committed. This proves
     * the availability-check logic the lock protects; it is not a test of concurrent/parallel
     * requests racing each other, which remains open (tracked for Phase 8's "20-concurrent-
     * request test on MySQL" in docs/MODULE_STATUS.md).
     */
    public function test_a_second_overlapping_booking_for_the_same_staff_member_is_refused(): void
    {
        $service = $this->service();
        $staff = $this->availableStaff();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($service->getKey(), $staff->getKey()))
            ->assertCreated();

        // Same staff member, starting 30 minutes into the first appointment's 75-minute slot.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', [
                ...$this->payload($service->getKey(), $staff->getKey()),
                'starts_at' => '2026-10-05 10:30:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    /**
     * Two different staff members may hold the same slot — only one groomer's calendar is
     * actually contended.
     */
    public function test_overlapping_bookings_for_different_staff_members_both_succeed(): void
    {
        $service = $this->service();
        $staffA = $this->availableStaff();
        $staffB = $this->availableStaff();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($service->getKey(), $staffA->getKey()))
            ->assertCreated();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload($service->getKey(), $staffB->getKey()))
            ->assertCreated();

        $this->assertSame(2, Appointment::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $serviceId, ?int $staffMemberId = null, ?int $customerId = null): array
    {
        return [
            'customer_id' => $customerId ?? $this->customer->getKey(),
            'pet_id' => $this->pet->getKey(),
            'service_id' => $serviceId,
            'staff_member_id' => $staffMemberId,
            // 2026-10-05 is a Monday, matching the 09:00-17:00 BusinessHour row from setUp().
            'starts_at' => '2026-10-05 10:00:00',
        ];
    }

    private function service(): Service
    {
        // 60 minutes + 15 buffer = 75, fits inside the 09:00-17:00 window with room to spare.
        return Service::factory()->create();
    }

    private function availableStaff(): StaffMember
    {
        $staff = StaffMember::factory()->create();
        $staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);

        return $staff;
    }
}

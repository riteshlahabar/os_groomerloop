<?php

namespace Modules\Scheduling\Tests\Feature;

use App\Domain\DayOfWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\Service;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Pets\Models\Pet;
use Modules\Scheduling\Contracts\AppointmentScheduler;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Models\BusinessHour;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The seam Booking (§12, `D-023`) will build directly on, instead of a second appointment engine.
 *
 * Tested as a contract in its own right, independent of HTTP, the same way `StaffDirectoryContractTest`
 * and `ServiceCatalogContractTest` prove the seam other modules build on.
 */
final class AppointmentSchedulerContractTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_it_summarises_an_appointment_without_handing_over_the_model(): void
    {
        $appointment = $this->appointment();

        $summary = $this->scheduler()->find($appointment->getKey());

        $this->assertNotNull($summary);
        $this->assertSame($appointment->customer_id, $summary->customerId);
        $this->assertFalse(method_exists($summary, 'save'));
    }

    public function test_exists_and_find_agree(): void
    {
        $appointment = $this->appointment();

        $this->assertTrue($this->scheduler()->exists($appointment->getKey()));
        $this->assertNotNull($this->scheduler()->find($appointment->getKey()));

        $this->assertFalse($this->scheduler()->exists(9_999_999));
        $this->assertNull($this->scheduler()->find(9_999_999));
    }

    public function test_appointments_for_returns_only_overlapping_rows_for_that_staff_member(): void
    {
        $staff = StaffMember::factory()->create();
        $inRange = $this->appointment(staffMemberId: $staff->getKey(), start: '2026-10-05 10:00:00');
        $outOfRange = $this->appointment(staffMemberId: $staff->getKey(), start: '2026-11-05 10:00:00');

        $found = $this->scheduler()->appointmentsFor(
            $staff->getKey(),
            new \DateTimeImmutable('2026-10-01'),
            new \DateTimeImmutable('2026-10-31'),
        );

        $ids = array_map(fn ($a) => $a->id, $found);
        $this->assertContains($inRange->getKey(), $ids);
        $this->assertNotContains($outOfRange->getKey(), $ids);
    }

    public function test_book_reschedule_and_update_status_round_trip(): void
    {
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();

        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();
        $service = Service::factory()->create();

        $summary = $this->scheduler()->book([
            'customer_id' => $customer->getKey(),
            'pet_id' => $pet->getKey(),
            'service_id' => $service->getKey(),
            'staff_member_id' => null,
            'starts_at' => '2026-10-05 10:00:00',
        ]);

        $this->assertSame(AppointmentStatus::Requested, $summary->status);
        $this->assertTrue($this->scheduler()->isSlotAvailable($service->getKey(), null, new \DateTimeImmutable('2026-10-05 13:00:00')));

        $rescheduled = $this->scheduler()->reschedule($summary->id, new \DateTimeImmutable('2026-10-05 13:00:00'));
        $this->assertEquals(new \DateTimeImmutable('2026-10-05 13:00:00'), $rescheduled->startsAt);

        $updated = $this->scheduler()->updateStatus($summary->id, AppointmentStatus::Confirmed);
        $this->assertSame(AppointmentStatus::Confirmed, $updated->status);

        $cancelled = $this->scheduler()->cancel($summary->id);
        $this->assertSame(AppointmentStatus::Cancelled, $cancelled->status);
    }

    public function test_it_cannot_resolve_another_tenants_appointment(): void
    {
        $otherTenant = Tenant::factory()->create();

        $strangersAppointment = app(TenantContext::class)->runFor($otherTenant, function (): Appointment {
            $customer = Customer::factory()->create();
            $pet = Pet::factory()->of($customer->getKey())->create();
            $service = Service::factory()->create();

            return Appointment::factory()
                ->forCustomer($customer->getKey())
                ->forPet($pet->getKey())
                ->forService($service->getKey())
                ->create();
        });

        $this->assertFalse($this->scheduler()->exists($strangersAppointment->getKey()));
        $this->assertNull($this->scheduler()->find($strangersAppointment->getKey()));
    }

    /**
     * @param  int|null  $staffMemberId
     */
    private function appointment($staffMemberId = null, string $start = '2026-10-05 10:00:00'): Appointment
    {
        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();
        $service = Service::factory()->create();

        return Appointment::factory()
            ->forCustomer($customer->getKey())
            ->forPet($pet->getKey())
            ->forService($service->getKey())
            ->forStaffMember($staffMemberId)
            ->startingAt(Carbon::parse($start), 60)
            ->create();
    }

    private function scheduler(): AppointmentScheduler
    {
        return app(AppointmentScheduler::class);
    }
}

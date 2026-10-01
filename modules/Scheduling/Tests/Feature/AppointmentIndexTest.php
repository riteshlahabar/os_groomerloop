<?php

namespace Modules\Scheduling\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\Service;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Models\Pet;
use Modules\Scheduling\Models\Appointment;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The §11 calendar query (day/week/month "views" are just a from/to range the client computes),
 * server-side paginated per §33, with each cross-module name resolved for the client in one
 * response rather than a request per row.
 */
final class AppointmentIndexTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Customer $customer;

    private Pet $pet;

    private Service $service;

    private StaffMember $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();
        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->create();
        $this->pet = Pet::factory()->of($this->customer->getKey())->create();
        $this->service = Service::factory()->named('Full Groom')->create();
        $this->staff = StaffMember::factory()->named('Maria Lopez')->create();
    }

    public function test_the_index_resolves_every_cross_module_name(): void
    {
        $this->appointmentAt('2026-10-05 10:00:00');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/appointments')
            ->assertOk()
            ->assertJsonPath('data.0.customer_name', $this->customer->first_name.' '.$this->customer->last_name)
            ->assertJsonPath('data.0.pet_name', $this->pet->name)
            ->assertJsonPath('data.0.service_name', 'Full Groom')
            ->assertJsonPath('data.0.staff_member_name', 'Maria Lopez');
    }

    public function test_the_index_can_be_filtered_by_date_range(): void
    {
        $this->appointmentAt('2026-10-05 10:00:00');
        $this->appointmentAt('2026-11-05 10:00:00');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/appointments?'.http_build_query([
                'from' => '2026-10-01', 'to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_index_can_be_filtered_by_staff_member(): void
    {
        $other = StaffMember::factory()->create();
        $this->appointmentAt('2026-10-05 10:00:00');
        $this->appointmentWith($other, '2026-10-05 13:00:00');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/appointments?staff_member_id='.$this->staff->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.staff_member_id', $this->staff->getKey());
    }

    public function test_the_index_can_be_filtered_by_status(): void
    {
        $this->appointmentAt('2026-10-05 10:00:00');
        $cancelled = $this->appointmentAt('2026-10-05 13:00:00');
        $cancelled->update(['status' => 'cancelled']);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/appointments?'.http_build_query(['status' => ['cancelled']]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_pagination_is_capped_at_the_maximum_page_size(): void
    {
        Appointment::factory()
            ->count(5)
            ->forCustomer($this->customer->getKey())
            ->forPet($this->pet->getKey())
            ->forService($this->service->getKey())
            ->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/appointments?per_page=500')
            ->assertUnprocessable();
    }

    public function test_per_page_controls_the_page_size(): void
    {
        Appointment::factory()
            ->count(5)
            ->forCustomer($this->customer->getKey())
            ->forPet($this->pet->getKey())
            ->forService($this->service->getKey())
            ->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/appointments?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5);
    }

    private function appointmentAt(string $start): Appointment
    {
        return $this->appointmentWith($this->staff, $start);
    }

    private function appointmentWith(StaffMember $staff, string $start): Appointment
    {
        return Appointment::factory()
            ->forCustomer($this->customer->getKey())
            ->forPet($this->pet->getKey())
            ->forService($this->service->getKey())
            ->forStaffMember($staff->getKey())
            ->startingAt(Carbon::parse($start), 60)
            ->create();
    }
}

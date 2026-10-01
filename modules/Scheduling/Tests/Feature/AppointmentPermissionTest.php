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
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The calendar.view/appointments.view/appointments.manage/appointments.update_status matrix
 * (spec §5, §11). Owner, Manager and Front Desk share one permission set here; Groomer is
 * narrower (read + status progression only); Marketing holds none of it.
 */
final class AppointmentPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);

        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();
    }

    public static function rolesThatCanFullyManageAppointments(): array
    {
        return [
            'owner' => [Role::Owner],
            'manager' => [Role::Manager],
            'front desk' => [Role::FrontDesk],
        ];
    }

    #[DataProvider('rolesThatCanFullyManageAppointments')]
    public function test_owner_manager_and_front_desk_can_fully_manage_appointments(Role $role): void
    {
        $user = $this->userWith($role);
        $appointment = $this->appointment();

        $this->actingAs($user)->getJson('/api/v1/appointments')->assertOk();
        $this->actingAs($user)->getJson("/api/v1/appointments/{$appointment->getKey()}")->assertOk();
        $this->actingAs($user)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}", ['customer_notes' => 'Noted'])
            ->assertOk();
        $this->actingAs($user)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/reschedule", ['starts_at' => '2026-10-05 13:00:00'])
            ->assertOk();
        $this->actingAs($user)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'confirmed'])
            ->assertOk();

        $fresh = $this->appointment();
        $this->actingAs($user)->deleteJson("/api/v1/appointments/{$fresh->getKey()}")->assertOk();
    }

    public function test_a_groomer_can_read_but_not_manage_appointments(): void
    {
        $groomer = $this->userWith(Role::Groomer);
        $appointment = $this->appointment();

        $this->actingAs($groomer)->getJson('/api/v1/appointments')->assertOk();
        $this->actingAs($groomer)->getJson("/api/v1/appointments/{$appointment->getKey()}")->assertOk();

        $this->actingAs($groomer)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}", ['customer_notes' => 'Noted'])
            ->assertForbidden();
        $this->actingAs($groomer)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/reschedule", ['starts_at' => '2026-10-05 13:00:00'])
            ->assertForbidden();
        $this->actingAs($groomer)
            ->postJson('/api/v1/appointments', [])
            ->assertForbidden();
        $this->actingAs($groomer)
            ->deleteJson("/api/v1/appointments/{$appointment->getKey()}")
            ->assertForbidden();
    }

    public function test_marketing_cannot_reach_the_calendar_at_all(): void
    {
        $marketing = $this->userWith(Role::Marketing);
        $appointment = $this->appointment();

        $this->actingAs($marketing)->getJson('/api/v1/appointments')->assertForbidden();
        $this->actingAs($marketing)->getJson("/api/v1/appointments/{$appointment->getKey()}")->assertForbidden();
        $this->actingAs($marketing)->getJson('/api/v1/business-hours')->assertForbidden();
        $this->actingAs($marketing)
            ->getJson('/api/v1/availability?service_id=1&starts_at=2026-10-05 10:00:00')
            ->assertForbidden();
        $this->actingAs($marketing)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'confirmed'])
            ->assertForbidden();
    }

    public function test_the_calendar_is_reachable_without_any_plan_at_all(): void
    {
        $tenant = Tenant::factory()->create();
        $user = app(TenantContext::class)->runFor(
            $tenant,
            fn (): User => User::factory()->memberOf($tenant, Role::Owner)->create()
        );

        $this->actingAs($user)->getJson('/api/v1/appointments')->assertOk();
    }

    private function appointment(): Appointment
    {
        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();
        $service = Service::factory()->create();

        return Appointment::factory()
            ->forCustomer($customer->getKey())
            ->forPet($pet->getKey())
            ->forService($service->getKey())
            ->startingAt(Carbon::parse('2026-10-05 10:00:00'), 60)
            ->create();
    }

    private function userWith(Role $role): User
    {
        return User::factory()->memberOf($this->tenant, $role)->create();
    }
}

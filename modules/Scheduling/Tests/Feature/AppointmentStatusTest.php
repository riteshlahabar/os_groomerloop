<?php

namespace Modules\Scheduling\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * The §11 state machine over HTTP, and the authorization split that makes
 * `appointments.update_status` narrower than `appointments.manage` actually hold (spec §5): a
 * Groomer may progress their own day but must not cancel or no-show a booking through the same
 * endpoint.
 */
final class AppointmentStatusTest extends TestCase
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

    public function test_requested_can_move_to_confirmed(): void
    {
        $appointment = $this->appointment();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->getKey(),
            'from_status' => 'requested',
            'to_status' => 'confirmed',
        ]);
    }

    public function test_an_invalid_jump_is_refused(): void
    {
        $appointment = $this->appointment();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'in-service'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_checked_in_requires_a_staff_member_to_already_be_assigned(): void
    {
        $appointment = $this->appointment(staffMemberId: null);
        $appointment->update(['status' => 'confirmed']);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'checked-in'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_checked_in_succeeds_once_a_staff_member_is_assigned(): void
    {
        $staff = StaffMember::factory()->create();
        $appointment = $this->appointment(staffMemberId: $staff->getKey());
        $appointment->update(['status' => 'confirmed']);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'checked-in'])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked-in');
    }

    public function test_a_terminal_status_accepts_no_further_transition(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['status' => 'cancelled']);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'confirmed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /**
     * Re-asserting the current status is idempotent, the same reasoning `SubscriptionStatus`
     * uses for replayed webhooks.
     */
    public function test_reasserting_the_current_status_is_a_no_op(): void
    {
        $appointment = $this->appointment();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'requested'])
            ->assertOk();

        $this->assertDatabaseMissing('appointment_status_history', [
            'appointment_id' => $appointment->getKey(),
            'to_status' => 'requested',
        ]);
    }

    // --- The authorization split: appointments.update_status vs. appointments.manage ---------

    public function test_a_groomer_can_progress_an_appointment_through_physical_states(): void
    {
        $staff = StaffMember::factory()->create();
        $appointment = $this->appointment(staffMemberId: $staff->getKey());
        $appointment->update(['status' => 'confirmed']);

        $groomer = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($groomer)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'checked-in'])
            ->assertOk();

        $this->actingAs($groomer)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'in-service'])
            ->assertOk();

        $this->actingAs($groomer)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'completed'])
            ->assertOk();
    }

    /**
     * The policy nuance this session added: a Groomer holds appointments.update_status but not
     * appointments.manage, and cancelling/no-showing a booking is a scheduling decision, not a
     * physical-progression one — the route's permission middleware alone cannot express "this
     * status, but not that one", so `AppointmentPolicy::updateStatus()` closes it.
     */
    public function test_a_groomer_cannot_cancel_an_appointment_through_the_status_endpoint(): void
    {
        $appointment = $this->appointment();
        $groomer = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($groomer)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'cancelled'])
            ->assertForbidden();

        $this->assertSame('requested', $appointment->refresh()->status->value);
    }

    public function test_a_groomer_cannot_mark_an_appointment_a_no_show_through_the_status_endpoint(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['status' => 'confirmed']);
        $groomer = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($groomer)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'no-show'])
            ->assertForbidden();
    }

    public function test_a_manager_can_cancel_through_the_status_endpoint(): void
    {
        $appointment = $this->appointment();
        $manager = User::factory()->memberOf($this->tenant, Role::Manager)->create();

        $this->actingAs($manager)
            ->putJson("/api/v1/appointments/{$appointment->getKey()}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancelling_through_destroy_requires_appointments_manage(): void
    {
        $appointment = $this->appointment();
        $groomer = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($groomer)
            ->deleteJson("/api/v1/appointments/{$appointment->getKey()}")
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/appointments/{$appointment->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    private function appointment(int|false|null $staffMemberId = false): Appointment
    {
        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();
        $service = Service::factory()->create();

        return Appointment::factory()
            ->forCustomer($customer->getKey())
            ->forPet($pet->getKey())
            ->forService($service->getKey())
            ->forStaffMember($staffMemberId === false ? StaffMember::factory()->create()->getKey() : $staffMemberId)
            ->create();
    }
}

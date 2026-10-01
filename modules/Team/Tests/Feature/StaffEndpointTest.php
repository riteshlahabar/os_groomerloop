<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Catalog\Models\Service;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Team\Domain\StaffStatus;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The team roster over HTTP (spec §23).
 */
final class StaffEndpointTest extends TestCase
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

    public function test_a_staff_member_can_be_created_with_no_login(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/staff', [
                'display_name' => 'Maria Lopez',
                'job_title' => 'Senior Groomer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'Maria Lopez')
            ->assertJsonPath('data.status', StaffStatus::Active->value)
            ->assertJsonPath('data.user_id', null);

        $this->assertDatabaseHas('staff_members', [
            'display_name' => 'Maria Lopez',
            'tenant_id' => $this->tenant->getKey(),
            'user_id' => null,
        ]);

        $event = AuditEvent::query()->where('event', 'staff.created')->firstOrFail();
        $this->assertFalse($event->properties['linked_to_user']);
    }

    public function test_display_name_is_required(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/staff', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('display_name');
    }

    public function test_a_staff_member_can_be_created_with_services_assigned(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/staff', [
                'display_name' => 'Maria Lopez',
                'service_ids' => [$service->getKey()],
            ])
            ->assertCreated()
            ->assertJsonPath('data.service_ids', [$service->getKey()]);
    }

    public function test_an_unknown_service_id_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/staff', [
                'display_name' => 'Maria Lopez',
                'service_ids' => [9_999_999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_ids');
    }

    public function test_a_staff_member_can_be_shown(): void
    {
        $staff = StaffMember::factory()->named('Maria Lopez')->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/staff/{$staff->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Maria Lopez')
            ->assertJsonPath('data.service_ids', []);
    }

    public function test_a_staff_member_can_be_updated(): void
    {
        $staff = StaffMember::factory()->named('Maria Lopez')->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$staff->getKey()}", ['job_title' => 'Lead Groomer'])
            ->assertOk()
            ->assertJsonPath('data.job_title', 'Lead Groomer');

        $event = AuditEvent::query()->where('event', 'staff.updated')->firstOrFail();
        $this->assertSame(['job_title'], $event->properties['changed']);
    }

    /**
     * The bug fix: `status` reaching the mass-update endpoint must do nothing at all, not even
     * silently succeed. Status only ever changes through the dedicated destroy/reactivate
     * endpoints, which fire their own audit events.
     */
    public function test_status_cannot_be_changed_through_a_plain_update(): void
    {
        $staff = StaffMember::factory()->named('Maria Lopez')->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$staff->getKey()}", [
                'job_title' => 'Lead Groomer',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', StaffStatus::Active->value);

        $this->assertSame(StaffStatus::Active, $staff->refresh()->status);
        $this->assertDatabaseMissing('audit_events', ['event' => 'staff.deactivated']);
    }

    public function test_destroying_a_staff_member_deactivates_rather_than_deletes(): void
    {
        $staff = StaffMember::factory()->named('Maria Lopez')->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/staff/{$staff->getKey()}")
            ->assertNoContent();

        $this->assertDatabaseHas('staff_members', [
            'id' => $staff->getKey(),
            'status' => StaffStatus::Inactive->value,
        ]);

        $event = AuditEvent::query()->where('event', 'staff.deactivated')->firstOrFail();
        $this->assertFalse($event->properties['still_has_login']);
    }

    public function test_a_deactivated_staff_member_can_be_reactivated(): void
    {
        $staff = StaffMember::factory()->named('Maria Lopez')->inactive()->create();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/staff/{$staff->getKey()}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', StaffStatus::Active->value);

        AuditEvent::query()->where('event', 'staff.reactivated')->firstOrFail();
    }

    public function test_reactivating_an_already_active_staff_member_is_a_no_op(): void
    {
        $staff = StaffMember::factory()->named('Maria Lopez')->create();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/staff/{$staff->getKey()}/reactivate")
            ->assertOk();

        $this->assertDatabaseMissing('audit_events', ['event' => 'staff.reactivated']);
    }
}

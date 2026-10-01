<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Team\Models\StaffMember;
use Modules\Team\Models\StaffTimeOff;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Absences (spec §23 "availability"): holiday, sickness, an afternoon out.
 */
final class StaffTimeOffTest extends TestCase
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

    public function test_an_absence_can_be_scheduled(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/staff/{$staff->getKey()}/time-off", [
                'starts_at' => '2026-12-24',
                'ends_at' => '2026-12-27',
                'is_all_day' => true,
                'reason' => 'Christmas',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_all_day', true);

        $this->assertDatabaseCount('staff_time_off', 1);

        $event = AuditEvent::query()->where('event', 'staff.time_off_scheduled')->firstOrFail();
        $this->assertTrue($event->properties['reason_given']);
    }

    /**
     * Sickness detail is the employee's business — an audit log readable by anyone holding
     * `audit.view` is not where it belongs, so the reason text itself is never recorded there.
     */
    public function test_an_absence_with_no_reason_records_only_that_one_was_not_given(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/staff/{$staff->getKey()}/time-off", [
                'starts_at' => '2026-12-24',
                'ends_at' => '2026-12-27',
            ])
            ->assertCreated();

        $event = AuditEvent::query()->where('event', 'staff.time_off_scheduled')->firstOrFail();
        $this->assertFalse($event->properties['reason_given']);
        $this->assertArrayNotHasKey('reason', $event->properties);
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/staff/{$staff->getKey()}/time-off", [
                'starts_at' => '2026-12-27',
                'ends_at' => '2026-12-24',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_an_absence_can_be_cancelled(): void
    {
        $staff = StaffMember::factory()->create();
        $absence = StaffTimeOff::factory()->for($staff, 'staffMember')->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/staff/{$staff->getKey()}/time-off/{$absence->getKey()}")
            ->assertNoContent();

        $this->assertDatabaseMissing('staff_time_off', ['id' => $absence->getKey()]);

        AuditEvent::query()->where('event', 'staff.time_off_cancelled')->firstOrFail();
    }

    /**
     * Two staff members in the same salon are the same tenant, so the tenant scope alone does not
     * prove a time-off id actually belongs to the staff member named in the URL.
     */
    public function test_cancelling_another_staff_members_absence_through_the_url_is_refused(): void
    {
        $staff = StaffMember::factory()->named('Maria')->create();
        $otherStaff = StaffMember::factory()->named('Devon')->create();
        $othersAbsence = StaffTimeOff::factory()->for($otherStaff, 'staffMember')->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/staff/{$staff->getKey()}/time-off/{$othersAbsence->getKey()}")
            ->assertNotFound();

        $this->assertDatabaseHas('staff_time_off', ['id' => $othersAbsence->getKey()]);
    }
}

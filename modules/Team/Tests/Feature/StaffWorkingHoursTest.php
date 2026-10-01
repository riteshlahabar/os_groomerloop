<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * A groomer's normal week (spec §23 "working hours").
 */
final class StaffWorkingHoursTest extends TestCase
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

    public function test_a_weeks_shifts_can_be_set(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$staff->getKey()}/working-hours", [
                'shifts' => [
                    ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'],
                    ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.working_hours');

        $this->assertDatabaseCount('staff_working_hours', 2);

        AuditEvent::query()->where('event', 'staff.working_hours_changed')->firstOrFail();
    }

    /**
     * An empty array is valid and is how a salon takes someone off the rota without their
     * leaving — the opposite of an error.
     */
    public function test_an_empty_rota_is_accepted_and_means_no_hours(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$staff->getKey()}/working-hours", ['shifts' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.working_hours');

        $event = AuditEvent::query()->where('event', 'staff.working_hours_changed')->firstOrFail();
        $this->assertTrue($event->properties['no_hours']);
    }

    public function test_overlapping_shifts_on_the_same_day_are_refused(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$staff->getKey()}/working-hours", [
                'shifts' => [
                    ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00'],
                    ['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '17:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shifts');

        $this->assertDatabaseCount('staff_working_hours', 0);
    }

    /**
     * Touching is not overlapping — a shift ending at 13:00 and another starting at 13:00 is a
     * perfectly ordinary lunch boundary.
     */
    public function test_back_to_back_shifts_are_not_overlapping(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$staff->getKey()}/working-hours", [
                'shifts' => [
                    ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00'],
                    ['day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '17:00'],
                ],
            ])
            ->assertOk();
    }

    public function test_setting_hours_replaces_the_whole_rota(): void
    {
        $staff = StaffMember::factory()->create();

        $this->actingAs($this->owner)->putJson("/api/v1/staff/{$staff->getKey()}/working-hours", [
            'shifts' => [['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']],
        ])->assertOk();

        $this->actingAs($this->owner)->putJson("/api/v1/staff/{$staff->getKey()}/working-hours", [
            'shifts' => [['day_of_week' => 2, 'starts_at' => '10:00', 'ends_at' => '14:00']],
        ])->assertOk();

        $this->assertDatabaseCount('staff_working_hours', 1);
        $this->assertDatabaseHas('staff_working_hours', ['day_of_week' => 2]);
    }
}

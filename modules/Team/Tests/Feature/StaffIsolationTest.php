<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Service;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Team\Contracts\StaffDirectory;
use Modules\Team\Models\StaffMember;
use Modules\Team\Models\StaffTimeOff;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Tenant isolation, proven through route model binding (D-014) — never only an explicit query.
 */
final class StaffIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $owner;

    private StaffMember $strangersStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        $this->otherTenant = Tenant::factory()->create();

        $this->strangersStaff = app(TenantContext::class)->runFor(
            $this->otherTenant,
            fn (): StaffMember => StaffMember::factory()->named('Their Groomer')->create()
        );

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_another_businesss_staff_member_cannot_be_read(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/staff/{$this->strangersStaff->getKey()}")
            ->assertNotFound();
    }

    public function test_another_businesss_staff_member_cannot_be_updated(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$this->strangersStaff->getKey()}", ['job_title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertNotSame('Hijacked', $this->strangersStaff->refresh()->job_title);
    }

    public function test_another_businesss_staff_member_cannot_be_deactivated(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/staff/{$this->strangersStaff->getKey()}")
            ->assertNotFound();

        $this->assertSame('active', $this->strangersStaff->refresh()->status->value);
    }

    public function test_another_businesss_staff_member_cannot_be_reactivated(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/v1/staff/{$this->strangersStaff->getKey()}/reactivate")
            ->assertNotFound();
    }

    public function test_another_businesss_staff_member_cannot_have_hours_set(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/staff/{$this->strangersStaff->getKey()}/working-hours", ['shifts' => []])
            ->assertNotFound();
    }

    public function test_another_businesss_staff_member_cannot_have_time_off_scheduled(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/v1/staff/{$this->strangersStaff->getKey()}/time-off", [
                'starts_at' => '2026-12-24',
                'ends_at' => '2026-12-27',
            ])
            ->assertNotFound();
    }

    public function test_the_staff_list_shows_only_this_businesss_team(): void
    {
        StaffMember::factory()->named('Mine')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/staff')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.display_name', 'Mine');
    }

    public function test_search_cannot_find_another_businesss_staff_member(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?search=Their')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Another business's user id must not be linkable to a staff record here — the custom
     * tenant-scoped validation rule, not a bare `exists:users,id`.
     */
    public function test_another_businesss_user_cannot_be_linked_to_a_staff_member(): void
    {
        $otherTenant = Tenant::factory()->create();
        $strangersUser = app(TenantContext::class)->runFor(
            $otherTenant,
            fn (): User => User::factory()->memberOf($otherTenant, Role::Groomer)->create()
        );

        $this->actingAs($this->owner)
            ->postJson('/api/v1/staff', [
                'display_name' => 'New Hire',
                'user_id' => $strangersUser->getKey(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    /**
     * Another business's service id must not be assignable as eligibility here (D-017) — validated
     * through Catalog's contract, never a bare `exists:services,id`.
     */
    public function test_another_businesss_service_cannot_be_assigned_as_eligibility(): void
    {
        $otherTenant = Tenant::factory()->create();
        $strangersService = app(TenantContext::class)->runFor(
            $otherTenant,
            fn (): Service => Service::factory()->create()
        );

        $this->actingAs($this->owner)
            ->postJson('/api/v1/staff', [
                'display_name' => 'New Hire',
                'service_ids' => [$strangersService->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_ids');
    }

    public function test_the_staff_directory_contract_cannot_resolve_another_businesss_staff_member(): void
    {
        $directory = app(StaffDirectory::class);

        $this->assertFalse($directory->exists($this->strangersStaff->getKey()));
        $this->assertNull($directory->find($this->strangersStaff->getKey()));
        $this->assertFalse($directory->isAssignable($this->strangersStaff->getKey()));
        $this->assertFalse($directory->canPerform($this->strangersStaff->getKey(), 1));
    }

    public function test_cancelling_time_off_across_a_tenant_boundary_is_refused(): void
    {
        $mine = StaffMember::factory()->create();

        $othersAbsence = app(TenantContext::class)->runFor(
            $this->otherTenant,
            fn (): StaffTimeOff => StaffTimeOff::factory()->for($this->strangersStaff, 'staffMember')->create()
        );

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/staff/{$mine->getKey()}/time-off/{$othersAbsence->getKey()}")
            ->assertNotFound();
    }
}

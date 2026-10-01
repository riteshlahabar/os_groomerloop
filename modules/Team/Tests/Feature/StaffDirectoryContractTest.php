<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Service;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Team\Actions\SyncStaffServices;
use Modules\Team\Contracts\StaffDirectory;
use Modules\Team\Models\StaffMember;
use Modules\Team\Services\EloquentStaffDirectory;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The seam Scheduling and Booking will read staff availability through (D-007).
 *
 * Tested as a contract in its own right, independent of HTTP, because these are the methods the
 * appointment engine will build on directly.
 */
final class StaffDirectoryContractTest extends TestCase
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

    public function test_it_summarises_a_staff_member_without_handing_over_the_model(): void
    {
        $staff = StaffMember::factory()->named('Maria Lopez')->create();

        $summary = $this->directory()->find($staff->getKey());

        $this->assertNotNull($summary);
        $this->assertSame('Maria Lopez', $summary->displayName);
        $this->assertTrue($summary->isAssignable);
        $this->assertFalse(method_exists($summary, 'save'));
    }

    /**
     * No restrictions recorded means a groomer does everything — the common case, and the reason
     * a solo groomer never has to tick every service against their own name.
     */
    public function test_a_staff_member_with_no_eligibility_rows_can_perform_anything(): void
    {
        $staff = StaffMember::factory()->create();
        $service = Service::factory()->create();

        $this->assertTrue($this->directory()->canPerform($staff->getKey(), $service->getKey()));
    }

    public function test_eligibility_restricts_to_the_assigned_services_only(): void
    {
        $staff = StaffMember::factory()->create();
        $allowed = Service::factory()->create();
        $notAllowed = Service::factory()->create();

        app(SyncStaffServices::class)->execute($staff, [$allowed->getKey()]);

        $directory = $this->directory();

        $this->assertTrue($directory->canPerform($staff->getKey(), $allowed->getKey()));
        $this->assertFalse($directory->canPerform($staff->getKey(), $notAllowed->getKey()));
    }

    public function test_an_inactive_staff_member_can_perform_nothing(): void
    {
        $staff = StaffMember::factory()->inactive()->create();
        $service = Service::factory()->create();

        $this->assertFalse($this->directory()->canPerform($staff->getKey(), $service->getKey()));
        $this->assertFalse($this->directory()->isAssignable($staff->getKey()));
    }

    /**
     * The opposite default from eligibility: no rota rows means not at work, because offering a
     * staff member with no shifts would be worse than offering nobody.
     */
    public function test_a_staff_member_with_no_working_hours_is_never_available(): void
    {
        $staff = StaffMember::factory()->create();

        $this->assertFalse(
            $this->directory()->isAvailableAt($staff->getKey(), new DateTimeImmutable('2026-10-05 10:00:00'), 60)
        );
    }

    public function test_a_staff_member_is_available_inside_a_shift_that_accommodates_the_booking(): void
    {
        $staff = StaffMember::factory()->create();
        $staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);

        // 2026-10-05 is a Monday.
        $this->assertTrue(
            $this->directory()->isAvailableAt($staff->getKey(), new DateTimeImmutable('2026-10-05 10:00:00'), 60)
        );
    }

    public function test_time_off_overrides_an_otherwise_available_shift(): void
    {
        $staff = StaffMember::factory()->create();
        $staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $staff->timeOff()->create(['starts_at' => '2026-10-05 00:00:00', 'ends_at' => '2026-10-06 00:00:00']);

        $this->assertFalse(
            $this->directory()->isAvailableAt($staff->getKey(), new DateTimeImmutable('2026-10-05 10:00:00'), 60)
        );
    }

    public function test_has_any_ignores_inactive_staff(): void
    {
        $this->assertFalse($this->directory()->hasAny());

        $staff = StaffMember::factory()->inactive()->create();
        $this->assertFalse($this->directory()->hasAny());

        $staff->update(['status' => 'active']);
        $this->assertTrue($this->directory()->hasAny());
    }

    public function test_it_finds_a_staff_member_by_their_linked_user(): void
    {
        $user = User::factory()->memberOf($this->tenant, Role::Groomer)->create();
        StaffMember::factory()->named('Maria')->linkedToUser($user->getKey())->create();

        $summary = $this->directory()->findByUser($user->getKey());

        $this->assertNotNull($summary);
        $this->assertSame('Maria', $summary->displayName);
    }

    /**
     * A fresh instance each time, the same lesson Catalog's and Entitlements' contract tests
     * teach: the container's singleton memoises per request, and that memo outliving a status
     * change inside one test would make this test lie.
     */
    private function directory(): StaffDirectory
    {
        return new EloquentStaffDirectory;
    }
}

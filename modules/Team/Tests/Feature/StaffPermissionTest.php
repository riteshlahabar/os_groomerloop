<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The staff.view/staff.manage matrix — deliberately separate from Identity's team.view/
 * team.manage, which gate user invitations and role changes rather than staff records.
 */
final class StaffPermissionTest extends TestCase
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

    public static function rolesThatReadTheRoster(): array
    {
        return [
            'owner' => [Role::Owner],
            'manager' => [Role::Manager],
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
        ];
    }

    #[DataProvider('rolesThatReadTheRoster')]
    public function test_everyone_working_in_the_salon_except_marketing_can_read_the_roster(Role $role): void
    {
        $staff = StaffMember::factory()->create();
        $user = $this->userWith($role);

        $this->actingAs($user)->getJson('/api/v1/staff')->assertOk();
        $this->actingAs($user)->getJson("/api/v1/staff/{$staff->getKey()}")->assertOk();
    }

    public function test_marketing_cannot_read_the_roster(): void
    {
        $staff = StaffMember::factory()->create();
        $user = $this->userWith(Role::Marketing);

        $this->actingAs($user)->getJson('/api/v1/staff')->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/staff/{$staff->getKey()}")->assertForbidden();
    }

    public static function rolesThatMayNotManageStaff(): array
    {
        return [
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
            'marketing' => [Role::Marketing],
        ];
    }

    #[DataProvider('rolesThatMayNotManageStaff')]
    public function test_only_an_owner_or_manager_may_manage_staff(Role $role): void
    {
        $staff = StaffMember::factory()->create();
        $user = $this->userWith($role);

        $this->actingAs($user)
            ->postJson('/api/v1/staff', ['display_name' => 'New Hire'])
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson("/api/v1/staff/{$staff->getKey()}", ['job_title' => 'Changed'])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/api/v1/staff/{$staff->getKey()}")
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson("/api/v1/staff/{$staff->getKey()}/working-hours", ['shifts' => []])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson("/api/v1/staff/{$staff->getKey()}/time-off", [
                'starts_at' => '2026-12-24', 'ends_at' => '2026-12-27',
            ])
            ->assertForbidden();
    }

    public function test_a_manager_can_run_the_team(): void
    {
        $user = $this->userWith(Role::Manager);

        $this->actingAs($user)
            ->postJson('/api/v1/staff', ['display_name' => 'New Hire'])
            ->assertCreated();

        $staff = StaffMember::query()->firstOrFail();

        $this->actingAs($user)
            ->putJson("/api/v1/staff/{$staff->getKey()}", ['job_title' => 'Groomer'])
            ->assertOk();
    }

    public function test_the_roster_is_reachable_without_any_plan_at_all(): void
    {
        $tenant = Tenant::factory()->create();

        $user = app(TenantContext::class)->runFor(
            $tenant,
            fn (): User => User::factory()->memberOf($tenant, Role::Owner)->create()
        );

        $this->actingAs($user)->getJson('/api/v1/staff')->assertOk();
    }

    private function userWith(Role $role): User
    {
        return User::factory()->memberOf($this->tenant, $role)->create();
    }
}

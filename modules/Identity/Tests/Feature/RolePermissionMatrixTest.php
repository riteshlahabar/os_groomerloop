<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Permission;
use Modules\Identity\Domain\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The full allow/deny matrix for the six roles of spec §5.
 *
 * Every role is checked against every permission, so nothing is left implicit. Writing the
 * expectations out longhand here is the point: it makes an accidental widening of a role — the
 * easiest privilege-escalation bug to introduce and the hardest to notice — fail the build.
 */
final class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Role => the permissions that role is expected to hold. Anything absent must be denied.
     *
     * @return array<string, array{Role, list<Permission>}>
     */
    public static function matrix(): array
    {
        $owner = array_values(array_filter(
            Permission::all(),
            static fn (Permission $p): bool => $p !== Permission::AdministerPlatform
        ));

        return [
            'owner holds everything but platform administration' => [Role::Owner, $owner],

            'manager runs operations but not billing or settings' => [Role::Manager, [
                Permission::ViewCustomers, Permission::ManageCustomers,

                // Bulk customer movement is a Manager-and-above capability (spec §8).
                Permission::ExportCustomers, Permission::ImportCustomers,

                Permission::ViewPets, Permission::ManagePets,
                Permission::ViewServices, Permission::ManageServices,
                Permission::ViewCalendar,
                Permission::ViewAppointments, Permission::ManageAppointments,
                Permission::UpdateAppointmentStatus,
                Permission::ViewTeam,
                Permission::ViewReports,
            ]],

            'groomer reads the day and moves appointments through statuses' => [Role::Groomer, [
                Permission::ViewCustomers,
                Permission::ViewPets,
                Permission::ViewServices,
                Permission::ViewCalendar,
                Permission::ViewAppointments,
                Permission::UpdateAppointmentStatus,
            ]],

            'front desk books and manages customers' => [Role::FrontDesk, [
                Permission::ViewCustomers, Permission::ManageCustomers,
                Permission::ViewPets, Permission::ManagePets,
                Permission::ViewServices,
                Permission::ViewCalendar,
                Permission::ViewAppointments, Permission::ManageAppointments,
                Permission::UpdateAppointmentStatus,
            ]],

            'marketing touches presence and growth, never the calendar' => [Role::Marketing, [
                Permission::ViewCustomers,
                Permission::ManageWebsite,
                Permission::ManageGrowth,
                Permission::ViewReports,
            ]],

            'platform admin gets no tenant data at all' => [Role::PlatformAdmin, [
                Permission::AdministerPlatform,
                Permission::ViewAuditLog,
            ]],
        ];
    }

    /**
     * @param  list<Permission>  $expected
     */
    #[DataProvider('matrix')]
    public function test_role_holds_exactly_its_expected_permissions(Role $role, array $expected): void
    {
        $user = User::factory()->withRole($role)->create();

        foreach (Permission::all() as $permission) {
            $shouldHold = in_array($permission, $expected, strict: true);

            $this->assertSame(
                $shouldHold,
                $user->hasPermission($permission),
                sprintf(
                    '%s should %s %s',
                    $role->value,
                    $shouldHold ? 'hold' : 'NOT hold',
                    $permission->value
                )
            );
        }
    }

    public function test_a_user_with_no_role_holds_no_permissions(): void
    {
        $user = User::factory()->create(['role' => null]);

        foreach (Permission::all() as $permission) {
            $this->assertFalse(
                $user->hasPermission($permission),
                "A user with no role must not hold {$permission->value}."
            );
        }
    }

    public function test_no_tenant_role_can_administer_the_platform(): void
    {
        foreach (Role::assignableWithinTenant() as $role) {
            $this->assertFalse(
                $role->grants(Permission::AdministerPlatform),
                "{$role->value} must never hold platform administration."
            );
        }
    }

    public function test_only_the_owner_can_manage_billing_and_settings(): void
    {
        foreach (Role::cases() as $role) {
            $expected = $role === Role::Owner;

            $this->assertSame($expected, $role->grants(Permission::ManageBilling));
            $this->assertSame($expected, $role->grants(Permission::ManageSettings));
            $this->assertSame($expected, $role->grants(Permission::ManageTeam));
        }
    }

    public function test_platform_admin_is_not_assignable_by_a_business(): void
    {
        $this->assertNotContains(Role::PlatformAdmin->value, Role::assignableValues());
        $this->assertCount(5, Role::assignableWithinTenant());
    }

    public function test_every_permission_is_registered_as_a_gate_ability(): void
    {
        $owner = User::factory()->withRole(Role::Owner)->create();

        // Proves the Permission enum and the Gate stay in step, so `$user->can('...')` never
        // silently returns false because an ability was never defined.
        foreach (Permission::all() as $permission) {
            $this->assertSame(
                $owner->hasPermission($permission),
                $owner->can($permission->value),
                "Gate ability [{$permission->value}] disagrees with the role matrix."
            );
        }
    }
}

<?php

namespace Modules\Identity\Domain;

/**
 * The six roles of spec §5, and the single authoritative map from role to capability.
 *
 * This enum is the only place the authorization matrix is written down. Nothing else in the
 * codebase compares against a role name — policies, middleware and controllers all ask about a
 * Permission instead, so a change here changes the whole product consistently.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Groomer = 'groomer';
    case FrontDesk = 'front_desk';
    case Marketing = 'marketing';

    /**
     * GroomerLoop staff, not a grooming business's staff. Belongs to no tenant.
     */
    case PlatformAdmin = 'platform_admin';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner / Admin',
            self::Manager => 'Manager',
            self::Groomer => 'Groomer / Staff',
            self::FrontDesk => 'Front Desk',
            self::Marketing => 'Marketing / Managed Growth',
            self::PlatformAdmin => 'GroomerLoop Admin',
        };
    }

    /**
     * The capabilities this role carries.
     *
     * Note what the Owner does NOT get: AdministerPlatform. A business owner runs their own
     * business, never the platform. And note what PlatformAdmin does not get: any tenant data
     * permission at all. Support access into a tenant account is spec §31, is out of MVP scope,
     * and when it arrives it must be an explicit, audited, time-bound grant — not a side effect
     * of holding a role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => array_values(array_filter(
                Permission::all(),
                static fn (Permission $p): bool => $p !== Permission::AdministerPlatform
            )),

            self::Manager => [
                Permission::ViewCustomers, Permission::ManageCustomers,
                Permission::ExportCustomers, Permission::ImportCustomers,
                Permission::ViewPets, Permission::ManagePets,
                Permission::ViewServices, Permission::ManageServices,
                Permission::ViewCalendar,
                Permission::ViewAppointments, Permission::ManageAppointments,
                Permission::UpdateAppointmentStatus,
                Permission::ViewTeam,
                Permission::ViewReports,
            ],

            self::Groomer => [
                Permission::ViewCustomers,
                Permission::ViewPets,
                Permission::ViewServices,
                Permission::ViewCalendar,
                Permission::ViewAppointments,
                Permission::UpdateAppointmentStatus,
            ],

            self::FrontDesk => [
                Permission::ViewCustomers, Permission::ManageCustomers,
                Permission::ViewPets, Permission::ManagePets,
                Permission::ViewServices,
                Permission::ViewCalendar,
                Permission::ViewAppointments, Permission::ManageAppointments,
                Permission::UpdateAppointmentStatus,
            ],

            self::Marketing => [
                Permission::ViewCustomers,
                Permission::ManageWebsite,
                Permission::ManageGrowth,
                Permission::ViewReports,
            ],

            self::PlatformAdmin => [
                Permission::AdministerPlatform,
                Permission::ViewAuditLog,
            ],
        };
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), strict: true);
    }

    public function isPlatform(): bool
    {
        return $this === self::PlatformAdmin;
    }

    /**
     * Roles a business may assign to its own people.
     *
     * Excludes PlatformAdmin, so no tenant can escalate one of its users into GroomerLoop staff.
     *
     * @return list<self>
     */
    public static function assignableWithinTenant(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $role): bool => ! $role->isPlatform()
        ));
    }

    /**
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::assignableWithinTenant()
        );
    }
}

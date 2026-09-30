<?php

namespace Modules\Identity\Domain;

/**
 * Every capability the product gates on.
 *
 * Permissions are checked, roles are assigned. Application code asks
 * "may this user manage appointments", never "is this user a manager" — so adding a role, or
 * moving a capability between roles, never means hunting for role-name comparisons scattered
 * through controllers and policies.
 *
 * The dotted values are also Laravel gate ability names, so `$user->can('customers.manage')`
 * and the `permission:` middleware both work without a translation layer.
 */
enum Permission: string
{
    case ViewCustomers = 'customers.view';
    case ManageCustomers = 'customers.manage';

    /**
     * Separate from ManageCustomers because spec §8 asks for "import/export with
     * permissions", and bulk is a different risk from single-record editing.
     *
     * A front desk that can add and edit customers all day is doing its job; the same
     * account downloading the entire customer book in one request, or overwriting it from a
     * spreadsheet, is the shape of both an insider leak and an honest catastrophe. So these
     * are held only by Owner and Manager.
     */
    case ExportCustomers = 'customers.export';
    case ImportCustomers = 'customers.import';

    case ViewPets = 'pets.view';
    case ManagePets = 'pets.manage';

    case ViewServices = 'services.view';
    case ManageServices = 'services.manage';

    case ViewCalendar = 'calendar.view';
    case ViewAppointments = 'appointments.view';
    case ManageAppointments = 'appointments.manage';

    /**
     * Narrower than ManageAppointments on purpose.
     *
     * A groomer moves an appointment through checked-in, in-service and completed (spec §11)
     * but must not reschedule or cancel it. Separating these keeps that distinction expressible
     * when Phase 7 builds the appointment engine.
     */
    case UpdateAppointmentStatus = 'appointments.update_status';

    case ViewTeam = 'team.view';
    case ManageTeam = 'team.manage';

    case ViewBilling = 'billing.view';
    case ManageBilling = 'billing.manage';

    case ManageSettings = 'settings.manage';
    case ManageWebsite = 'website.manage';

    case ViewReports = 'reports.view';
    case ManageGrowth = 'growth.manage';

    case ViewAuditLog = 'audit.view';

    /**
     * Platform operations only — never granted to a tenant role.
     */
    case AdministerPlatform = 'platform.administer';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}

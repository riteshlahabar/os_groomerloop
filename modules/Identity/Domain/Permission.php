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

    /**
     * Spec §9 asks for "internal staff notes with permissions" — a visibility level of its own,
     * distinct from the notes the customer supplied.
     *
     * Held by everyone who handles the animal, and that deliberately includes Groomer, who has
     * no ManagePets: "muzzle required", "bit a groomer in March" is safety information, and the
     * person holding the clippers is both the one who needs to read it and the one who learns
     * it. Marketing is the exception — §5 scopes it to growth modules, and staff commentary
     * about a customer's dog has no business in a campaign tool.
     *
     * Granting read and write together is the honest shape: a groomer who may read the handling
     * history is the right person to add to it, and splitting them would mean the observation
     * never gets written down.
     */
    case AccessInternalPetNotes = 'pets.internal_notes';

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

    /**
     * Staff *records* (spec §23: groomers, their hours, their time off) — distinct from
     * ViewTeam/ManageTeam, which gate Identity's user-invitation and role-change endpoints. A
     * manager running the rota day to day is a different, lower-stakes capability than granting
     * someone a login and a system role.
     */
    case ViewStaff = 'staff.view';
    case ManageStaff = 'staff.manage';

    case ViewBilling = 'billing.view';
    case ManageBilling = 'billing.manage';

    case ManageSettings = 'settings.manage';
    case ManageWebsite = 'website.manage';

    /**
     * Spec §13's Messages screen. Two capabilities, because reading the delivery log and causing a
     * message to go out to a customer are different stakes: `messages.view` is "what did we send and
     * did it arrive", `messages.send` is a deliberate outbound message (today, retrying a failed one).
     * See `D-031` for why the §6 Messages nav item needed its own permission at all.
     */
    case ViewMessages = 'messages.view';
    case SendMessages = 'messages.send';

    case ViewReports = 'reports.view';
    case ManageGrowth = 'growth.manage';

    case ViewAuditLog = 'audit.view';

    /**
     * Platform operations only — never granted to a tenant role.
     */
    case AdministerPlatform = 'platform.administer';

    /**
     * Managing GroomerLoop's own staff accounts — held by Super Admin alone (`D-035`).
     *
     * Split out of `AdministerPlatform` because granting the platform privilege is a different
     * risk from using it: an operator who can suspend a business is doing their job, while one
     * who can mint another operator can make that privilege permanent and spread it. It is the
     * one §31 capability worth withholding from a support hire, which is why it is the only line
     * between Admin and Super Admin.
     */
    case ManagePlatformAdmins = 'platform.manage_admins';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}

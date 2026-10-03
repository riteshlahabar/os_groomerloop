<?php

namespace Modules\SuperAdmin\Actions;

use App\Models\User;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Domain\Role;
use Modules\SuperAdmin\Exceptions\CannotRemovePlatformAdmin;

/**
 * Remove a GroomerLoop staff account (`D-034`).
 *
 * Two refusals, both about not locking everyone out of the platform console:
 *
 *   1. **Never the last one.** With no platform admin left, nothing can reach `/platform` again
 *      and the only way back is shell access to run `platform-admin:create` — which `D-011`'s
 *      hosting notes say is not reliably available (SSH closed on the shared cPanel host).
 *   2. **Never yourself.** Removing your own account mid-session is almost always a misclick, and
 *      the recovery is the same awkward one. A colleague can still remove you.
 *
 * A hard delete rather than a deactivate flag: a platform admin owns no tenant data, so there is
 * nothing for the row to keep pointing at. The audit event is the record that the account existed
 * — `audit_events` is append-only (`D-001`), so the history survives the row.
 */
final class DeletePlatformAdmin
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(User $admin, User $actor): void
    {
        if ($admin->getKey() === $actor->getKey()) {
            throw CannotRemovePlatformAdmin::self();
        }

        // Only Super Admins are counted: they are the tier that can grant the privilege back, so
        // losing the last one is the state nothing can recover from inside the console. Removing
        // the last plain Admin costs nothing — a Super Admin can always create another.
        if ($admin->role === Role::PlatformAdmin && $this->remainingSuperAdmins() <= 1) {
            throw CannotRemovePlatformAdmin::lastOne();
        }

        $this->audit->record('platform_admin.removed', null, [
            'removed_user_id' => $admin->getKey(),
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role?->value,
        ]);

        $admin->delete();
    }

    private function remainingSuperAdmins(): int
    {
        return User::query()->where('role', Role::PlatformAdmin->value)->count();
    }
}

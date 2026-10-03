<?php

namespace Modules\SuperAdmin\Actions;

use App\Models\User;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Domain\Role;

/**
 * Creates a GroomerLoop staff account (spec §5's `PlatformAdmin` role) — belongs to no tenant,
 * so there is no registration flow for it (D-007: a tenant's own registration cannot be how
 * GroomerLoop's own staff get an account, or any business could create one for itself).
 *
 * Two callers since `D-034`: `platform-admin:create`, which bootstraps the **first** account on a
 * fresh host and is the only way in when none exists, and `PlatformAdminController::store`, which
 * lets an existing GroomerLoop Admin add colleagues from `/platform/admins`. `D-029` originally
 * made this console-only on the reasoning that a role this powerful should need shell access; the
 * owner overrode that, so the HTTP path exists and is gated on `permission:platform.administer` —
 * which only an existing platform admin holds, so the privilege can be shared but never
 * self-granted from a tenant account.
 */
final class CreatePlatformAdmin
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(string $name, string $email, string $password): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);

        // Neither is fillable, by design — set directly, the same as RegisterBusiness does for
        // an Owner.
        $user->tenant_id = null;
        $user->role = Role::PlatformAdmin;
        $user->save();

        // Outside any tenant context, same as AuthenticateUser::recordLogin() for this role.
        $this->audit->record('platform_admin.created', $user, ['email' => $email]);

        return $user;
    }
}

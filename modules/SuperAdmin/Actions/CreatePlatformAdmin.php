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
 * The only caller is `platform-admin:create` (console-only, by design): a role this powerful
 * must be granted by someone with shell access to the production host, never by an HTTP
 * endpoint, however permission-gated.
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

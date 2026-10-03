<?php

namespace Modules\SuperAdmin\Actions;

use App\Models\User;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Domain\Role;
use Modules\SuperAdmin\Exceptions\CannotRemovePlatformAdmin;

/**
 * Change a GroomerLoop staff account's name, email, password or tier (`D-034`, `D-035`).
 *
 * `tenant_id` is never touched: a platform account belongs to no business, and letting this screen
 * attach one would create a user nothing else in the product expects. The **tier** may change —
 * promoting a support hire to Super Admin, or stepping someone down, is ordinary staff
 * administration — but only between the two platform roles, and never in a way that leaves nobody
 * able to manage staff again.
 */
final class UpdatePlatformAdmin
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  name, email, role (optional), and password
     *                                            (optional — omitted or blank keeps the current
     *                                            one, the same rule the mail settings screens use
     *                                            for a stored credential)
     */
    public function execute(User $admin, array $attributes): User
    {
        $password = $attributes['password'] ?? null;
        $role = $this->resolveRole($attributes['role'] ?? null);
        unset($attributes['password'], $attributes['role']);

        $before = ['name' => $admin->name, 'email' => $admin->email, 'role' => $admin->role?->value];

        $admin->fill($attributes);

        if ($password !== null && $password !== '') {
            $admin->password = $password;
        }

        if ($role !== null && $role !== $admin->role) {
            // Stepping the last Super Admin down is the same lockout as deleting them — nobody
            // would be left who can promote anyone back. Refused with the same message.
            if ($admin->role === Role::PlatformAdmin && $this->superAdminCount() <= 1) {
                throw CannotRemovePlatformAdmin::lastOne();
            }

            // Not fillable, by design — set directly, like CreatePlatformAdmin does.
            $admin->role = $role;
        }

        $admin->save();

        $this->audit->record('platform_admin.updated', $admin, [
            'before' => $before,
            'after' => ['name' => $admin->name, 'email' => $admin->email, 'role' => $admin->role?->value],
            // Never the password itself — only that it changed, the precedent set by
            // UpdatePlatformMailSettings.
            'password_changed' => $password !== null && $password !== '',
        ]);

        return $admin;
    }

    /**
     * A role from the request, accepted only if it is one of GroomerLoop's own. Anything else —
     * including a tenant role — is ignored rather than applied.
     */
    private function resolveRole(mixed $value): ?Role
    {
        $role = is_string($value) ? Role::tryFrom($value) : null;

        return $role !== null && in_array($role, Role::platform(), strict: true) ? $role : null;
    }

    private function superAdminCount(): int
    {
        return User::query()->where('role', Role::PlatformAdmin->value)->count();
    }
}

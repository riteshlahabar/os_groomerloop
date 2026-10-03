<?php

namespace Modules\Identity\Concerns;

use Modules\Identity\Domain\Permission;
use Modules\Identity\Domain\Role;

/**
 * Gives a user a role and the permissions that role carries.
 *
 * The cast is registered here rather than in the model, so the Identity module owns the mapping
 * from the stored string to the Role enum and the shared User model does not need to know about
 * it. Eloquent calls initializeHasRole() from the model constructor.
 *
 * Fails closed: a user with no role has no permissions at all. That matters because tenant_id
 * and role are both set by trusted code paths (registration, invitation acceptance) rather than
 * by request input, so a missing role means something went wrong and should grant nothing.
 *
 * @property Role|null $role
 */
trait HasRole
{
    public function initializeHasRole(): void
    {
        $this->mergeCasts(['role' => Role::class]);
    }

    public function hasRole(Role ...$roles): bool
    {
        return $this->role !== null && in_array($this->role, $roles, strict: true);
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->role?->grants($permission) ?? false;
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Role::Owner);
    }

    /**
     * GroomerLoop's own staff, of either tier (`D-035`).
     *
     * Means "belongs to no tenant and operates the platform", which is true of Super Admin and
     * Admin alike — so callers asking "is this a platform account" keep working unchanged after
     * the split. "May this account manage other staff" is a different question and is asked as a
     * permission (`platform.manage_admins`), never by comparing the role.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->hasRole(Role::PlatformAdmin, Role::PlatformSupport);
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_map(
            static fn (Permission $permission): string => $permission->value,
            $this->role?->permissions() ?? []
        );
    }
}

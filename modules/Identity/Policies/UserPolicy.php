<?php

namespace Modules\Identity\Policies;

use App\Models\User;
use Modules\Identity\Domain\Permission;

/**
 * Who may see and manage the people in a business (spec §23).
 *
 * Cross-tenant access is not checked here and does not need to be: the tenant scope means a user
 * from another business is never loaded in the first place, so these methods only ever decide
 * between colleagues.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ViewTeam);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::ViewTeam) || $actor->is($target);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::ManageTeam) || $actor->is($target);
    }

    /**
     * Changing a role is separated from updating a profile, and nobody may change their own.
     *
     * Self-assignment is the classic privilege-escalation route: anyone who could edit their own
     * role could promote themselves. An owner who wants to step down promotes a colleague first,
     * and that colleague demotes them.
     */
    public function updateRole(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return $actor->hasPermission(Permission::ManageTeam);
    }

    public function delete(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return $actor->hasPermission(Permission::ManageTeam);
    }
}

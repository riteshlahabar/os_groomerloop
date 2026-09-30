<?php

namespace Modules\Identity\Policies;

use App\Models\User;
use Modules\Identity\Domain\Permission;
use Modules\Identity\Models\Invitation;

/**
 * Inviting and withdrawing team invitations is part of managing the team, so it rides on the same
 * permission rather than inventing a second one.
 */
final class InvitationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageTeam);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageTeam);
    }

    public function delete(User $actor, Invitation $invitation): bool
    {
        return $actor->hasPermission(Permission::ManageTeam);
    }
}

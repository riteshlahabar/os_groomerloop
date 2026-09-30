<?php

namespace Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Domain\Role;

/**
 * Changes a team member's role, with the two guards that stop a business breaking itself.
 */
final class ChangeUserRole
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(User $target, Role $newRole, User $actor): User
    {
        $this->refusePlatformRole($newRole);
        $this->refuseRemovingTheLastOwner($target, $newRole);

        $previous = $target->role;

        $target->role = $newRole;
        $target->save();

        // Invariant #8: permission changes are exactly the kind of action that must be
        // reconstructable afterwards, so the previous role is recorded, not just the new one.
        $this->audit->record('user.role_changed', $target, [
            'from' => $previous?->value,
            'to' => $newRole->value,
            'changed_by_id' => $actor->getKey(),
        ]);

        return $target;
    }

    private function refusePlatformRole(Role $role): void
    {
        if ($role->isPlatform()) {
            throw ValidationException::withMessages([
                'role' => 'That role cannot be assigned by a business.',
            ]);
        }
    }

    /**
     * Only the Owner role carries ManageBilling and ManageSettings. Demoting the last owner would
     * leave a business that nobody can bill, configure, or promote anyone else in — locked out of
     * itself with no way back except support intervention.
     */
    private function refuseRemovingTheLastOwner(User $target, Role $newRole): void
    {
        if (! $target->isOwner() || $newRole === Role::Owner) {
            return;
        }

        // Tenant-scoped by the global scope, so this counts owners of this business only.
        $remainingOwners = User::query()
            ->where('role', Role::Owner->value)
            ->whereKeyNot($target->getKey())
            ->count();

        if ($remainingOwners === 0) {
            throw ValidationException::withMessages([
                'role' => 'This business must have at least one owner. Promote someone else first.',
            ]);
        }
    }
}

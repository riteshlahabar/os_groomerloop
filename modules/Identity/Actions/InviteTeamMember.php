<?php

namespace Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Domain\Role;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Notifications\TeamInvitation;
use Modules\Tenancy\Support\TenantContext;

/**
 * Invites someone to join the current business with a given role (spec §23).
 *
 * The invitation carries the role, so acceptance never lets the invitee choose their own
 * permissions.
 */
final class InviteTeamMember
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(User $inviter, string $email, Role $role): Invitation
    {
        $this->refusePlatformRole($role);
        $this->refuseUnusableEmail($email);

        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($inviter, $email, $role, $token): Invitation {
            // Any earlier unaccepted invitation to this address is withdrawn, so a re-invite
            // cannot leave two live tokens for one person.
            Invitation::query()->pending()->where('email', $email)->delete();

            $invitation = Invitation::create([
                'email' => $email,
                'role' => $role,
                'token_hash' => Invitation::hashToken($token),
                'invited_by_id' => $inviter->getKey(),
                'expires_at' => now()->addDays(Invitation::LIFETIME_DAYS),
            ]);

            $this->audit->record('invitation.sent', $invitation, [
                'email' => $email,
                'role' => $role->value,
            ]);

            return $invitation;
        });

        // Sent only after the transaction commits: a plaintext token must never reach a
        // recipient for an invitation that was then rolled back.
        Notification::route('mail', $email)->notify(new TeamInvitation($token, $invitation));

        return $invitation;
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
     * One email address is one GroomerLoop account: users.email carries a global unique index.
     *
     * So this has to be checked across all tenants, not just the current one — otherwise a
     * tenant-scoped check would pass, the invitation would be sent, and acceptance would then
     * fail on the unique index with the invitee holding a token that can never work.
     */
    private function refuseUnusableEmail(string $email): void
    {
        $inThisBusiness = User::query()->where('email', $email)->exists();

        if ($inThisBusiness) {
            throw ValidationException::withMessages([
                'email' => 'That person is already a member of this business.',
            ]);
        }

        $elsewhere = $this->tenants->withoutTenancy(
            static fn (): bool => User::query()->where('email', $email)->exists()
        );

        if ($elsewhere) {
            throw ValidationException::withMessages([
                'email' => 'That email address already has a GroomerLoop account.',
            ]);
        }
    }
}

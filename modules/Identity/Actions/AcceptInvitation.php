<?php

namespace Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Models\Invitation;
use Modules\Tenancy\Support\TenantContext;

/**
 * Turns a valid invitation token into a user account inside the inviting business.
 *
 * Runs on a public, unauthenticated route: the invitee has no account yet, so there is no tenant
 * context and the invitation itself is the only thing that says which business they join.
 */
final class AcceptInvitation
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(string $token, string $name, string $password): User
    {
        $invitation = $this->findUsableInvitation($token);

        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = new User([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);

            // Both taken from the invitation, never from the request — the invitee cannot choose
            // which business they join or what role they get.
            $user->tenant_id = $invitation->tenant_id;
            $user->role = $invitation->role;
            $user->email_verified_at = now();
            $user->save();

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_by_id' => $user->getKey(),
            ])->save();

            $this->tenants->runFor($invitation->tenant, function () use ($invitation, $user): void {
                $this->audit->record('invitation.accepted', $user, [
                    'invitation_id' => $invitation->getKey(),
                    'role' => $invitation->role->value,
                ]);
            });

            return $user;
        });
    }

    /**
     * Looked up by token hash, so the plaintext token never needs to be stored or compared in
     * application code. A missing, expired, already-accepted or revoked invitation all produce
     * the same message — there is nothing to learn from the difference.
     */
    private function findUsableInvitation(string $token): Invitation
    {
        $invitation = $this->tenants->withoutTenancy(
            static fn (): ?Invitation => Invitation::query()
                ->where('token_hash', Invitation::hashToken($token))
                ->first()
        );

        if ($invitation === null || ! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation is no longer valid.',
            ]);
        }

        return $invitation;
    }
}

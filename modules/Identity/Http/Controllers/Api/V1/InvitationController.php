<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Actions\InviteTeamMember;
use Modules\Identity\Http\Requests\InviteTeamMemberRequest;
use Modules\Identity\Http\Resources\InvitationResource;
use Modules\Identity\Models\Invitation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Managing this business's outstanding team invitations.
 *
 * Accepting one is a different job with a different audience — an unauthenticated stranger with a
 * token — so it lives in its own controller.
 */
final class InvitationController
{
    public function index(): AnonymousResourceCollection
    {
        // Tenant-scoped by the global scope; paginated server-side per spec §33 rather than
        // returning an unbounded list.
        return InvitationResource::collection(
            Invitation::query()->latest()->paginate(25)
        );
    }

    public function store(InviteTeamMemberRequest $request, InviteTeamMember $invite): JsonResponse
    {
        $invitation = $invite->execute(
            inviter: $request->user(),
            email: $request->string('email')->toString(),
            role: $request->role(),
        );

        return InvitationResource::make($invitation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Invitation $invitation, AuditRecorder $audit): JsonResponse
    {
        $audit->record('invitation.revoked', $invitation, [
            'email' => $invitation->email,
            'revoked_by_id' => $request->user()->getKey(),
        ]);

        $invitation->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

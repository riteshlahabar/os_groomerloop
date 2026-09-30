<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Actions\AcceptInvitation;
use Modules\Identity\Http\Requests\AcceptInvitationRequest;
use Modules\Identity\Http\Resources\UserResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public: the invitee has no account yet, so this route is unauthenticated and has no tenant
 * middleware. The token is the only thing that decides which business they join.
 */
final class AcceptInvitationController
{
    public function __invoke(AcceptInvitationRequest $request, AcceptInvitation $accept): JsonResponse
    {
        $user = $accept->execute(
            token: $request->string('token')->toString(),
            name: $request->string('name')->toString(),
            password: $request->string('password')->toString(),
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return UserResource::make($user->load('tenant'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

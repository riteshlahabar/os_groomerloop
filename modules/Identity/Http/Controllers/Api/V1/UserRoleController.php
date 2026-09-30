<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Actions\ChangeUserRole;
use Modules\Identity\Http\Requests\UpdateUserRoleRequest;
use Modules\Identity\Http\Resources\UserResource;

/**
 * Changing a team member's role — its own controller because it is its own functionality, with
 * its own policy method and its own audit entry.
 */
final class UserRoleController
{
    public function __invoke(
        UpdateUserRoleRequest $request,
        User $user,
        ChangeUserRole $changeRole,
    ): JsonResponse {
        // Policy rather than middleware: this decision depends on which user is being changed
        // (nobody may change their own role), so it cannot be answered from the route alone.
        Gate::authorize('updateRole', $user);

        $updated = $changeRole->execute($user, $request->role(), $request->user());

        return UserResource::make($updated)->response();
    }
}

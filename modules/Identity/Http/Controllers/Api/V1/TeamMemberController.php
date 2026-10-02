<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Identity\Http\Requests\ListTeamMembersRequest;
use Modules\Identity\Http\Resources\TeamMemberResource;
use Modules\Identity\Services\TeamMemberIndex;

/**
 * Who can sign in to this business (spec §23 "Roles", "Permissions").
 *
 * The read half of `PUT /team/{user}/role`, which until now had none: the role-change endpoint
 * existed and was tested, but nothing could enumerate the users whose role there was to change,
 * so no interface could offer it. Its own controller, one functionality, per the standing rule.
 *
 * Gated `permission:team.view` at the route. §5 gives `team.view` to Owner and Manager; actually
 * *changing* a role needs `team.manage`, which is Owner alone — so a Manager sees the roster and
 * cannot edit it, which is the intended split rather than an oversight.
 */
final class TeamMemberController
{
    public function index(ListTeamMembersRequest $request, TeamMemberIndex $index): AnonymousResourceCollection
    {
        return TeamMemberResource::collection(
            $index->paginate($request->filters())
        );
    }
}

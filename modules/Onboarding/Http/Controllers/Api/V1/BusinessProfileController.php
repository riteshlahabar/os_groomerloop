<?php

namespace Modules\Onboarding\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Onboarding\Actions\UpdateBusinessProfile;
use Modules\Onboarding\Http\Requests\UpdateBusinessProfileRequest;
use Modules\Onboarding\Http\Resources\BusinessProfileResource;
use Modules\Onboarding\Models\BusinessProfile;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Spec §7 step 2: the details of the business itself.
 *
 * A singleton resource — one profile per business — so there is no {id} in the route and no
 * route model binding to get wrong. The tenant scope answers "whose profile" by itself.
 */
final class BusinessProfileController
{
    public function show(): JsonResponse
    {
        $profile = BusinessProfile::query()->first();

        // Null rather than 404: a business that has not reached §7 step 2 has no profile,
        // and that is a normal state the setup screen renders, not a missing page.
        return response()->json([
            'data' => $profile === null
                ? null
                : BusinessProfileResource::make($profile)->resolve(),
        ]);
    }

    public function update(UpdateBusinessProfileRequest $request, UpdateBusinessProfile $update): JsonResponse
    {
        $profile = $update->execute($request->profileAttributes());

        // Always 200, never 201.
        //
        // JsonResource::response() answers 201 when the underlying model was recently
        // created, which here would mean the very first save of a profile returned 201 and
        // every later one returned 200 — the same endpoint, the same URL, two statuses,
        // depending on history the client cannot see. For a singleton resource that upserts,
        // "created" is not a distinction worth making.
        return BusinessProfileResource::make($profile)
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_OK);
    }
}

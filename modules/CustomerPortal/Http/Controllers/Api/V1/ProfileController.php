<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\CustomerPortal\Http\Requests\UpdateSelfProfileRequest;
use Modules\CustomerPortal\Http\Resources\SelfProfileResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer's own profile (`D-043`) — the page that replaced the portal's Dashboard on
 * 2026-10-09 at the owner's request.
 *
 * Its own controller rather than more verbs on `MeController`: that one answers "who am I" for the
 * page chrome in three fields and is called on every portal page, including by §12's booking
 * wizard to decide whether it is in its signed-in shape. This one is the Profile screen's read and
 * write of the editable record. Splitting them keeps the cheap, hot call cheap.
 */
final class ProfileController
{
    public function show(Request $request, CustomerDirectory $customers): JsonResponse
    {
        $profile = $customers->selfProfileOf($this->customerId($request));

        // Null would mean an authenticated session whose customer row has since been deleted or
        // moved out of this tenant. Nothing to show, and nothing the page can do about it.
        if ($profile === null) {
            return response()->json(['message' => 'Profile not found.'], Response::HTTP_NOT_FOUND);
        }

        return SelfProfileResource::make($profile)->response();
    }

    public function update(UpdateSelfProfileRequest $request, CustomerDirectory $customers): JsonResponse
    {
        $customerId = $this->customerId($request);

        if (! $customers->updateSelfProfile($customerId, $request->validated())) {
            return response()->json(['message' => 'Profile not found.'], Response::HTTP_NOT_FOUND);
        }

        // The saved record, not the submitted values: whatever normalisation the contract applied
        // is what the form should now show.
        return SelfProfileResource::make($customers->selfProfileOf($customerId))->response();
    }

    /**
     * Always the session's customer, never a body or route value — the same rule `D-047` settled
     * for the booking endpoint. There is no id to pass here, so there is nothing to tamper with.
     */
    private function customerId(Request $request): int
    {
        return (int) $request->user('customer')->getAuthIdentifier();
    }
}

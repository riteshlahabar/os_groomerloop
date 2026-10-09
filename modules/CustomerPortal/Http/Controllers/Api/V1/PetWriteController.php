<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CustomerPortal\Http\Requests\SavePetRequest;
use Modules\Pets\Contracts\PetDirectory;
use Symfony\Component\HttpFoundation\Response;

/**
 * A customer adding or editing their own pets from the portal (`D-043`, built 2026-10-09).
 *
 * Separate from the read-only `PetController` beside it, which is one `__invoke` listing their
 * pets — one controller per functionality, and "show me my pets" and "change my pet's record" are
 * two.
 *
 * Both methods write through `PetDirectory` (`D-007` — never Pets' own Eloquent model), and
 * neither takes a customer id from anywhere but the session.
 */
final class PetWriteController
{
    public function store(SavePetRequest $request, PetDirectory $pets): JsonResponse
    {
        $petId = $pets->createForCustomer($this->customerId($request), $request->validated());

        return response()->json(['data' => ['id' => $petId]], Response::HTTP_CREATED);
    }

    public function update(SavePetRequest $request, PetDirectory $pets): JsonResponse
    {
        // Read by name, never as a loose method argument. This route has **two** parameters —
        // `customer/{tenant}/pets/{pet}` — and Laravel fills a non-class argument from the
        // leftovers positionally, so an `int $pet` argument here received the *tenant* id (1)
        // instead of the pet id. The symptom was a flat 404 on a pet the customer definitely
        // owns, because pet 1 is not theirs; it is the trap `PublicSiteController` already
        // documents, hit a second time, and this is the same fix.
        $petId = (int) $request->route('pet');

        // `updateForCustomer()` takes both ids and answers false rather than throwing when the pet
        // is not this customer's, so ownership cannot be forgotten here. 404, not 403: a customer
        // learns nothing about whether a pet id exists in this business at all — the same
        // reasoning `ResolvePublicTenant` applies to a suspended tenant.
        if (! $pets->updateForCustomer($petId, $this->customerId($request), $request->validated())) {
            return response()->json(['message' => 'Pet not found.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => ['id' => $petId]]);
    }

    private function customerId(Request $request): int
    {
        return (int) $request->user('customer')->getAuthIdentifier();
    }
}

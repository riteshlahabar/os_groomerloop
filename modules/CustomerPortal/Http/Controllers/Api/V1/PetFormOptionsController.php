<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Domain\PetSex;

/**
 * The fixed choices the portal's pet form needs: this business's species list, plus the two §9
 * enumerations.
 *
 * Why not reuse `GET /api/v1/public/{tenant}/pet-species`, which already returns the same list:
 * that route is **slug**-keyed (`ResolvePublicTenant`), and every portal route is **id**-keyed
 * (`ResolveCustomerTenant`, `D-043`), so a portal page would have to carry a second tenant key
 * just for one request. The list itself still comes from Pets' own contract either way, so there
 * is one source of truth and no duplicated query.
 *
 * Sex and coat type are enum cases rather than rows, so they could have been written into the
 * Blade — they are sent from here so the form's three dropdowns are built one way, and so adding a
 * `CoatType` case shows up in the portal with no view change.
 */
final class PetFormOptionsController
{
    public function __invoke(PetDirectory $pets): JsonResponse
    {
        return response()->json([
            'data' => [
                'species' => $pets->listSpecies(),
                // Enumerated from the cases themselves, with each case's own `label()`, so the
                // claim above is true: a new `CoatType` reaches the form with no edit here and
                // none in the Blade.
                'sexes' => array_map(
                    static fn (PetSex $sex): array => ['value' => $sex->value, 'label' => $sex->label()],
                    PetSex::cases(),
                ),
                'coat_types' => array_map(
                    static fn (CoatType $coat): array => ['value' => $coat->value, 'label' => $coat->label()],
                    CoatType::cases(),
                ),
            ],
        ]);
    }
}

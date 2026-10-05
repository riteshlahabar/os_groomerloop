<?php

namespace Modules\Booking\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Pets\Contracts\PetDirectory;

/**
 * The tenant's species list (spec §9, now a per-business table rather than a fixed enum —
 * 2026-10-05), for the public booking page's pet-species picker. No model to wrap in a Resource
 * — `PetDirectory::listSpecies()` already returns the `{id, name}` shape the picker needs.
 */
final class PublicPetSpeciesController
{
    public function __invoke(PetDirectory $pets): JsonResponse
    {
        return response()->json(['data' => $pets->listSpecies()]);
    }
}

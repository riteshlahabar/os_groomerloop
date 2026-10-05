<?php

namespace Modules\Pets\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Pets\Actions\EnsureDefaultSpecies;
use Modules\Pets\Actions\UpsertSpecies;
use Modules\Pets\Http\Requests\StoreSpeciesRequest;
use Modules\Pets\Http\Resources\SpeciesResource;
use Modules\Pets\Models\Species;
use Symfony\Component\HttpFoundation\Response;

/**
 * How a business names the kinds of animal it grooms (spec §9), added 2026-10-05 to replace
 * the fixed `dog`/`cat`/`other` list every tenant used to share.
 */
final class SpeciesController
{
    public function index(EnsureDefaultSpecies $ensureDefaults): AnonymousResourceCollection
    {
        $ensureDefaults->execute();

        return SpeciesResource::collection(
            Species::query()
                ->withCount('pets')
                ->orderBy('position')
                ->orderBy('name')
                ->paginate(100)
        );
    }

    /**
     * 200 rather than 201 when the species already existed, so a client asking twice can tell
     * that nothing new was made.
     */
    public function store(StoreSpeciesRequest $request, UpsertSpecies $upsert): JsonResponse
    {
        $species = $upsert->execute(
            $request->string('name')->toString(),
            (int) $request->input('position', 0),
        );

        abort_if($species === null, Response::HTTP_UNPROCESSABLE_ENTITY);

        return SpeciesResource::make($species)
            ->response()
            ->setStatusCode($species->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Unlike a service category, a pet's species is never optional — so deleting one still in
     * use is refused rather than left to null out, the one place this screen genuinely differs
     * from `/admin/settings/categories`'s "nothing is deleted" promise.
     */
    public function destroy(Species $species, AuditRecorder $audit): JsonResponse
    {
        $inUse = $species->pets()->count();

        if ($inUse > 0) {
            return response()->json([
                'message' => "{$inUse} pet(s) still use \"{$species->name}\" — reassign them to another species first.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $audit->record('pet_species.deleted', $species, ['name' => $species->name]);

        $species->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

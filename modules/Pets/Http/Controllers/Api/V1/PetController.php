<?php

namespace Modules\Pets\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Actions\ArchivePet;
use Modules\Pets\Actions\CreatePet;
use Modules\Pets\Actions\UpdatePet;
use Modules\Pets\Http\Requests\ListPetsRequest;
use Modules\Pets\Http\Requests\StorePetRequest;
use Modules\Pets\Http\Requests\UpdatePetRequest;
use Modules\Pets\Http\Resources\PetResource;
use Modules\Pets\Models\Pet;
use Modules\Pets\Services\PetIndex;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pet records (spec §9).
 *
 * The internal staff notes are a separate controller with a separate permission, and the pets of
 * one customer are a separate controller again — different use cases, and the difference between
 * this staying readable and becoming the file every CRM eventually grows.
 */
final class PetController
{
    public function index(
        ListPetsRequest $request,
        PetIndex $index,
        CustomerDirectory $customers,
    ): AnonymousResourceCollection {
        $pets = $index->paginate($request->filters());

        // Primes the directory's per-request memo with one query for the whole page, so rendering
        // "whose pet is this" per row costs nothing. Without it the list is a query per row — an
        // N+1 that strict mode cannot see, because it is not an Eloquent relationship.
        $customers->namesOf($pets->pluck('customer_id')->map(fn ($id): int => (int) $id)->all());

        return PetResource::collection($pets);
    }

    public function store(StorePetRequest $request, CreatePet $create): JsonResponse
    {
        $pet = $create->execute($request->customerId(), $request->petAttributes());

        return PetResource::make($pet)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Route model binding, resolved after ResolveTenant thanks to the global middleware priority
     * in bootstrap/app.php (D-014). Another business's pet is simply not found — 404, not 403, so
     * the response does not confirm the record exists.
     */
    public function show(Pet $pet): PetResource
    {
        return PetResource::make($pet);
    }

    public function update(UpdatePetRequest $request, Pet $pet, UpdatePet $update): PetResource
    {
        return PetResource::make($update->execute($pet, $request->petAttributes()));
    }

    /**
     * Archives rather than deletes (invariant #4): a pet carries grooming history, appointment
     * history and the notes that make the next groom go well.
     */
    public function destroy(Pet $pet, ArchivePet $archive): JsonResponse
    {
        $archive->execute($pet);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

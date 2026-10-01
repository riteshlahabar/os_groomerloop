<?php

namespace Modules\Pets\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Pets\Http\Requests\ListPetsRequest;
use Modules\Pets\Http\Resources\PetResource;
use Modules\Pets\Services\PetIndex;

/**
 * The pets of one customer — spec §9's "multiple pets linked to one customer".
 *
 * Its own controller rather than a filter on the pet list, because it is the shape every screen
 * in the product actually needs: the customer record, the booking form and the check-in screen
 * all ask "which animals does this family have". `{customer}` is a plain integer here, not a
 * bound model — binding it would mean Pets loading Crm's model, which is exactly the coupling the
 * boundary rule forbids. An id from another business matches no pets and returns an empty page.
 */
final class CustomerPetController
{
    public function __invoke(
        ListPetsRequest $request,
        int $customer,
        PetIndex $index,
    ): AnonymousResourceCollection {
        return PetResource::collection(
            $index->paginate([...$request->filters(), 'customer_id' => $customer])
        );
    }
}

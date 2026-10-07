<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\CustomerPortal\Http\Resources\PetResource;
use Modules\Pets\Contracts\PetDirectory;

final class PetController
{
    public function __invoke(Request $request, PetDirectory $pets): AnonymousResourceCollection
    {
        $customerId = (int) $request->user('customer')->getAuthIdentifier();

        return PetResource::collection($pets->summariesForCustomer($customerId));
    }
}

<?php

namespace Modules\Booking\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Booking\Http\Resources\PublicServiceResource;
use Modules\Catalog\Contracts\ServiceCatalog;

final class PublicServiceController
{
    public function index(ServiceCatalog $catalog): AnonymousResourceCollection
    {
        return PublicServiceResource::collection($catalog->bookableOnline());
    }
}

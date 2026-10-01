<?php

namespace Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Models\AppointmentAddOn;

/**
 * @property-read AppointmentAddOn $resource
 */
final class AppointmentAddOnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(ServiceCatalog::class)->find($this->resource->service_id);

        return [
            'service_id' => $this->resource->service_id,
            'service_name' => $service?->name,
            'price' => $service?->price(),
        ];
    }
}

<?php

namespace Modules\Automation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Automation\Models\AutomationRun;
use Modules\Crm\Contracts\CustomerDirectory;

/**
 * @property-read AutomationRun $resource
 */
final class AutomationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),

            'automation_key' => $this->resource->automation_key->value,
            'automation_label' => $this->resource->automation_key->label(),

            'customer_id' => $this->resource->customer_id,
            'customer_name' => $this->resource->customer_id === null
                ? null
                : app(CustomerDirectory::class)->nameOf($this->resource->customer_id),

            'appointment_id' => $this->resource->appointment_id,

            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}

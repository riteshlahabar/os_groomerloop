<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Models\Invoice;

/**
 * @property-read Invoice $resource
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'number' => $this->resource->number,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'description' => $this->resource->description,

            'subtotal_cents' => $this->resource->subtotal_cents,
            'tax_cents' => $this->resource->tax_cents,
            'total_cents' => $this->resource->total_cents,
            'total' => number_format($this->resource->totalInDollars(), 2, '.', ''),
            'currency' => $this->resource->currency,

            // What was actually bought, as recorded at the time — not what that plan costs
            // today. This is why the columns are denormalised onto the invoice.
            'plan_key' => $this->resource->plan_key,
            'plan_name' => $this->resource->plan_name,

            'issued_at' => $this->resource->issued_at?->toIso8601String(),
            'due_at' => $this->resource->due_at?->toIso8601String(),
            'paid_at' => $this->resource->paid_at?->toIso8601String(),

            // Surfaced so a business can see why a payment did not go through and fix it
            // themselves, rather than having to contact support to find out.
            'failure_code' => $this->resource->failure_code,
            'failure_message' => $this->resource->failure_message,
        ];
    }
}

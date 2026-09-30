<?php

namespace Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Models\Customer;

/**
 * @property-read Customer $resource
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),

            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->fullName(),

            'email' => $this->resource->email,
            'phone' => $this->resource->phone,

            'address_line_1' => $this->resource->address_line_1,
            'address_line_2' => $this->resource->address_line_2,
            'city' => $this->resource->city,
            'state' => $this->resource->state,
            'postal_code' => $this->resource->postal_code,
            'country' => $this->resource->country,

            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'source' => $this->resource->source?->value,
            'source_label' => $this->resource->source?->label(),

            'notes' => $this->resource->notes,

            // whenLoaded, so the index eager-loads tags once and a show that did not ask for
            // them does not trigger a query per customer.
            'tags' => CustomerTagResource::collection($this->whenLoaded('tags')),

            // Consent reported as the answer, not as raw flags. Every caller wants to know
            // "may I text this person", and computing that from four booleans in the client
            // is how invariant #9 gets broken by a screen that forgot the global opt-out.
            'consent' => [
                'opted_out' => $this->resource->hasOptedOut(),
                'opted_out_at' => $this->resource->opted_out_at?->toIso8601String(),
                'accepts_marketing' => (bool) $this->resource->accepts_marketing,
                'recorded_at' => $this->resource->consent_recorded_at?->toIso8601String(),
                'channels' => $this->channels(),
            ],

            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array{allowed: bool, marketing: bool}>
     */
    private function channels(): array
    {
        $channels = [];

        foreach (CommunicationChannel::all() as $channel) {
            $channels[$channel->value] = [
                'allowed' => $this->resource->allowsChannel($channel),
                'marketing' => $this->resource->allowsMarketingOn($channel),
            ];
        }

        return $channels;
    }
}

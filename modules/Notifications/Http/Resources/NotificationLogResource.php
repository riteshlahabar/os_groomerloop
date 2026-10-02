<?php

namespace Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Models\NotificationLog;

/**
 * @property-read NotificationLog $resource
 */
final class NotificationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),

            'type' => $this->resource->type->value,
            'type_label' => $this->resource->type->label(),
            'channel' => $this->resource->channel->value,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),

            'recipient' => $this->resource->recipient,
            'subject' => $this->resource->subject,
            'failure_reason' => $this->resource->failure_reason,

            'attempt' => $this->resource->attempt,
            'max_attempts' => NotificationLog::MAX_ATTEMPTS,
            'retry_of_id' => $this->resource->retry_of_id,

            // Whether the screen should offer a Retry button at all, decided by the model rather than
            // re-derived in JavaScript — a consent-skipped row must never be offered one.
            'is_retryable' => $this->resource->isRetryable(),

            'customer_id' => $this->resource->customer_id,

            // Attached by NotificationLogIndex through Crm's contract; absent on a single-row response
            // that did not go through it.
            'customer_name' => $this->when(
                isset($this->resource->customer_name),
                fn (): ?string => $this->resource->customer_name,
            ),

            'appointment_id' => $this->resource->appointment_id,

            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
